<?php

namespace Omnistate\Tests\Bridge;

use Omnistate\Bridge\Symfony\OmnistateBundle;
use Omnistate\Bridge\Symfony\Validator\Siren;
use Omnistate\Bridge\Symfony\Validator\Siret;
use Omnistate\Bridge\Symfony\Validator\VatNumber;
use Omnistate\Omnistate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Validator\Validation;

final class OmnistateBundleTest extends TestCase
{
    public function testEveryRegistryInstalledIsWiredWithTheRequesterAndTheCache(): void
    {
        $sent = [];
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setPublic(true)->setSynthetic(true);
        $container->register('cache.app', ArrayAdapter::class);
        $bundle = new OmnistateBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('omnistate', ['requester' => 'FR53901821074', 'ttl' => 60]);
        $container->compile();
        $container->set('http_client', new MockHttpClient(function (string $method, string $url, array $options) use (&$sent) {
            $sent[] = json_decode($options['body'] ?? 'null', true);

            return new MockResponse(file_get_contents(__DIR__.'/../../docker/harness/recorded/vies-FR53901821074.json'));
        }));

        $omnistate = $container->get(Omnistate::class);
        self::assertTrue($omnistate->vat('FR53901821074')->valid);
        self::assertTrue($omnistate->vat('FR53901821074')->valid);
        self::assertCount(1, $sent, 'the second answer from the cache');
        self::assertSame('53901821074', $sent[0]['requesterNumber'], 'the requester configured');
        self::assertTrue($omnistate->company('901821074') !== false, 'the French register wired (answering from the mock)');
    }

    public function testTheConstraints(): void
    {
        $validator = Validation::createValidator();

        self::assertCount(0, $validator->validate('901821074', new Siren()));
        self::assertCount(1, $validator->validate('901821075', new Siren()));
        self::assertCount(0, $validator->validate('90182107400019', new Siret()));
        self::assertCount(1, $validator->validate('90182107400018', new Siret()));
        self::assertCount(0, $validator->validate('FR53901821074', new VatNumber()));
        self::assertCount(1, $validator->validate('FR54901821074', new VatNumber()), 'its key wrong');
        self::assertCount(0, $validator->validate(null, new VatNumber()));
    }
}
