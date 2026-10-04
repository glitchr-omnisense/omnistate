<?php

namespace Omnistate\Tests;

use Omnistate\Bridge\Symfony\OmnistateBundle;
use Omnistate\Bridge\Symfony\Validator\Finess as FinessConstraint;
use Omnistate\Bridge\Symfony\Validator\Rpps as RppsConstraint;
use Omnistate\Exception\NotSupportedException;
use Omnistate\Identifier\Finess;
use Omnistate\Identifier\Rpps;
use Omnistate\Model\Facility;
use Omnistate\Model\Professional;
use Omnistate\Model\Qualification;
use Omnistate\Omnistate;
use Omnistate\Registry\FacilityRegistryInterface;
use Omnistate\Registry\ProfessionalRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Validator\Validation;

final class ProfessionalTest extends TestCase
{
    public function testRppsNumbers(): void
    {
        $rpps = Rpps::withKey('1000000001');

        self::assertTrue(Rpps::isValid($rpps));
        self::assertSame(11, \strlen($rpps));
        self::assertSame($rpps, Rpps::normalize('8'.$rpps), 'its IDNPS form');
        self::assertSame('8'.$rpps, Rpps::toIdnps($rpps));
        self::assertFalse(Rpps::isValid(substr($rpps, 0, 10).((int) $rpps[10] + 1) % 10), 'a wrong key');
        self::assertNull(Rpps::normalize('1234'));
    }

    public function testFinessNumbers(): void
    {
        self::assertTrue(Finess::isValid('580008803'));
        self::assertFalse(Finess::isValid('580008804'));
        self::assertSame('2A', Finess::department('2a 000 123 4'));
        self::assertNull(Finess::normalize('3A0001234'));
    }

    public function testAProfessionalIsAskedOfTheRegistryThatKnowsThatIdentifier(): void
    {
        $registry = $this->registry();
        $omnistate = new Omnistate(professionals: [$registry], cache: new ArrayAdapter());

        self::assertSame('Camille Exemple', $omnistate->professional('8 100 0000 0018')?->name());
        self::assertSame('Camille Exemple', $omnistate->professional('10000000018')?->name());
        self::assertSame(['10000000018'], $registry->asked, 'normalized, then answered from the cache');
        self::assertSame('Maison de santé', $omnistate->facility('670001234')?->name);
        self::assertCount(1, $omnistate->professionals('exemple', '67'));

        $this->expectException(NotSupportedException::class);
        $omnistate->professional('P-123');
    }

    public function testNoProfessionalRegistryInstalled(): void
    {
        $this->expectException(NotSupportedException::class);
        (new Omnistate())->facility('670001234');
    }

    public function testTheBundleWiresTheAnnuaireSanteWithItsKey(): void
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class)->setPublic(true)->setSynthetic(true);
        $container->register('cache.app', ArrayAdapter::class);
        $bundle = new OmnistateBundle();
        $container->registerExtension($bundle->getContainerExtension());
        $container->loadFromExtension('omnistate', ['annuaire_sante' => ['api_key' => 'k']]);
        $container->compile();
        $headers = [];
        $container->set('http_client', new MockHttpClient(function (string $method, string $url, array $options) use (&$headers) {
            $headers = $options['headers'];

            return new \Symfony\Component\HttpClient\Response\MockResponse('{"resourceType":"Bundle","type":"searchset","total":0}');
        }));

        self::assertNull($container->get(Omnistate::class)->professional('10000000017'), 'asked, and nobody by that number');
        self::assertContains('ESANTE-API-KEY: k', $headers);
    }

    public function testTheConstraints(): void
    {
        $validator = Validation::createValidator();

        self::assertCount(0, $validator->validate(Rpps::withKey('1000000001'), new RppsConstraint()));
        self::assertCount(1, $validator->validate('10000000010', new RppsConstraint()));
        self::assertCount(0, $validator->validate('580008803', new FinessConstraint()));
        self::assertCount(1, $validator->validate('580008800', new FinessConstraint()));
    }

    private function registry(): ProfessionalRegistryInterface
    {
        return new class implements ProfessionalRegistryInterface, FacilityRegistryInterface {
            public array $asked = [];

            public function name(): string
            {
                return 'fake';
            }

            public function supports(string $identifier): bool
            {
                return null !== Rpps::normalize($identifier);
            }

            public function professional(string $identifier): ?Professional
            {
                $this->asked[] = $identifier;

                return new Professional($identifier, 'Exemple', 'fake', 'Camille', profession: new Qualification('60', 'TRE_G15-ProfessionSante', 'Infirmier'));
            }

            public function search(string $name, ?string $postcode = null, int $limit = 10): array
            {
                return [$this->professional('10000000018')];
            }

            public function supportsFacility(string $identifier): bool
            {
                return null !== Finess::normalize($identifier);
            }

            public function facility(string $identifier): ?Facility
            {
                return new Facility($identifier, 'Maison de santé', 'fake');
            }
        };
    }
}
