<?php

namespace Omnistate\Tests\Identifier;

use Omnistate\Identifier\Siren;
use Omnistate\Identifier\Siret;
use Omnistate\Identifier\VatNumber;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
    public function testSiren(): void
    {
        self::assertTrue(Siren::isValid('552 032 534'));
        self::assertTrue(Siren::isValid('732829320'));
        self::assertFalse(Siren::isValid('552032535'), 'its key wrong');
        self::assertFalse(Siren::isValid('90182107'), 'eight digits');
        self::assertSame('552032534', Siren::normalize('552.032.534'));
    }

    public function testSiret(): void
    {
        self::assertTrue(Siret::isValid('552 032 534 00703'));
        self::assertFalse(Siret::isValid('55203253400704'));
        self::assertSame('552032534', Siret::siren('55203253400703'));
        // La Poste: the sum of the digits, a multiple of 5 - not Luhn.
        self::assertTrue(Siret::isValid('35600000000010'));
        self::assertFalse(Siret::isValid('35600000000011'));
        // Its head office, as the register gives it: Luhn-valid, its digits' sum 26.
        self::assertTrue(Siret::isValid('35600000000048'));
    }

    public function testAFrenchVatNumberIsItsSirenWithAKey(): void
    {
        self::assertSame('FR27552032534', Siren::toVatNumber('552032534'));
        self::assertSame('FR27552032534', VatNumber::normalize('552 032 534'), 'a SIREN is enough');
        self::assertSame('FR27552032534', VatNumber::normalize('55203253400703'), 'a SIRET too');
        self::assertTrue(VatNumber::isWellFormed('fr 27 552032534'));
        self::assertFalse(VatNumber::isWellFormed('FR28552032534'), 'the key does not agree with the SIREN');
    }

    public function testEachMemberStateHasItsShape(): void
    {
        self::assertSame('DE123456789', VatNumber::normalize('de 123-456-789'));
        self::assertSame('EL123456789', VatNumber::normalize('GR123456789'), 'Greece is EL in VIES');
        self::assertSame('NL123456789B01', VatNumber::normalize('NL123456789B01'));
        self::assertSame('ATU12345678', VatNumber::normalize('ATU12345678'));
        self::assertNull(VatNumber::normalize('DE12345678'), 'eight digits in Germany');
        self::assertNull(VatNumber::normalize('US123456789'), 'not a member state');
        self::assertNull(VatNumber::normalize(''));
    }
}
