<?php

namespace Omnistate\Tests;

use Omnistate\Exception\NotSupportedException;
use Omnistate\Model\Company;
use Omnistate\Model\CompanyStatus;
use Omnistate\Model\VatCheck;
use Omnistate\Omnistate;
use Omnistate\Registry\CompanyRegistryInterface;
use Omnistate\Registry\VatRegistryInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class OmnistateTest extends TestCase
{
    public function testACompanyIsAskedOfTheRegistryThatKnowsItsKindOfIdentifier(): void
    {
        $registry = $this->companies();
        $omnistate = new Omnistate([$registry]);

        self::assertSame('DANONE', $omnistate->company('552 032 534')?->name);
        self::assertSame('DANONE', $omnistate->company('FR27552032534')?->name, 'a French VAT number, by its SIREN');
        self::assertSame(['552032534', '552032534'], $registry->asked);

        $this->expectException(NotSupportedException::class);
        $omnistate->company('GB12345678');
    }

    public function testAnswersAreKeptInTheCache(): void
    {
        $registry = $this->companies();
        $omnistate = new Omnistate([$registry], cache: new ArrayAdapter());
        $omnistate->company('552032534');
        $omnistate->company('552032534');

        self::assertCount(1, $registry->asked, 'asked once');
    }

    public function testAVatNumberNoMemberStateWritesThatWayIsNotValidWithoutAskingAnyone(): void
    {
        $vat = $this->vat();
        $omnistate = new Omnistate(vat: [$vat], requester: 'FR27552032534');

        self::assertFalse($omnistate->vat('DE1234')->valid);
        self::assertFalse($omnistate->vat('FR28552032534')->valid, 'its key disagrees with its SIREN');
        self::assertSame([], $vat->asked);

        self::assertTrue($omnistate->vat('de 123 456 789')->valid);
        self::assertSame([['DE123456789', 'FR27552032534']], $vat->asked, 'normalized, with the requester');
    }

    /** @return CompanyRegistryInterface&object{asked: list<string>} */
    private function companies(): CompanyRegistryInterface
    {
        return new class implements CompanyRegistryInterface {
            /** @var list<string> */
            public array $asked = [];

            public function name(): string
            {
                return 'fake';
            }

            public function supports(string $identifier): bool
            {
                return (bool) preg_match('/^\d{9}(\d{5})?$/', $identifier);
            }

            public function company(string $identifier): Company
            {
                $this->asked[] = $identifier;

                return new Company($identifier, 'DANONE', CompanyStatus::ACTIVE, 'FR', 'fake');
            }

            public function search(string $query, int $limit = 10): array
            {
                return [];
            }
        };
    }

    /** @return VatRegistryInterface&object{asked: list<array{string, ?string}>} */
    private function vat(): VatRegistryInterface
    {
        return new class implements VatRegistryInterface {
            /** @var list<array{string, ?string}> */
            public array $asked = [];

            public function name(): string
            {
                return 'fake';
            }

            public function supports(string $number): bool
            {
                return true;
            }

            public function check(string $number, ?string $requester = null): VatCheck
            {
                $this->asked[] = [$number, $requester];

                return new VatCheck($number, true, new \DateTimeImmutable(), 'fake');
            }
        };
    }
}
