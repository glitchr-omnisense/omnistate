<?php

namespace Omnistate\Tests\Bridge;

use Omnisense\Contracts\Http\Data;
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
        if (!class_exists(\Omnistate\Vies\Vies::class)) {
            self::markTestSkipped('omnistate/vies is not installed.');
        }
        $sent = [];
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setPublic(true)->setSynthetic(true);
        $container->register('cache.app', ArrayAdapter::class);
        $bundle = new OmnistateBundle();
        $extension = $bundle->getContainerExtension();
        self::assertNotNull($extension);
        $container->registerExtension($extension);
        $container->loadFromExtension('omnistate', ['requester' => 'FR27552032534', 'ttl' => 60]);
        $container->compile();
        $container->set('http_client', new MockHttpClient(function (string $method, string $url, array $options) use (&$sent) {
            $body = $options['body'] ?? 'null';
            self::assertIsString($body);
            $sent[] = json_decode($body, true);

            return new MockResponse((string) file_get_contents(__DIR__.'/../../docker/harness/recorded/vies-FR27552032534.json'));
        }));

        $omnistate = $container->get(Omnistate::class);
        self::assertInstanceOf(Omnistate::class, $omnistate);
        self::assertTrue($omnistate->vat('FR27552032534')->valid);
        self::assertTrue($omnistate->vat('FR27552032534')->valid);
        self::assertCount(1, $sent, 'the second answer from the cache');
        self::assertSame('27552032534', Data::of($sent[0])->get('requesterNumber'), 'the requester configured');
        $omnistate->company('552032534'); // the French register wired (answering from the mock): no NotSupportedException
        $this->addToAssertionCount(1);
    }

    public function testTheConstraints(): void
    {
        $validator = Validation::createValidator();

        self::assertCount(0, $validator->validate('552032534', new Siren()));
        self::assertCount(1, $validator->validate('552032535', new Siren()));
        self::assertCount(0, $validator->validate('55203253400703', new Siret()));
        self::assertCount(1, $validator->validate('55203253400704', new Siret()));
        self::assertCount(0, $validator->validate('FR27552032534', new VatNumber()));
        self::assertCount(1, $validator->validate('FR28552032534', new VatNumber()), 'its key wrong');
        self::assertCount(0, $validator->validate(null, new VatNumber()));
    }
}
