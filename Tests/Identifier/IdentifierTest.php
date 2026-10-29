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
        self::assertTrue(Siren::isValid('901 821 074'));
        self::assertTrue(Siren::isValid('732829320'));
        self::assertFalse(Siren::isValid('901821075'), 'its key wrong');
        self::assertFalse(Siren::isValid('90182107'), 'eight digits');
        self::assertSame('901821074', Siren::normalize('901.821.074'));
    }

    public function testSiret(): void
    {
        self::assertTrue(Siret::isValid('901 821 074 00019'));
        self::assertFalse(Siret::isValid('90182107400018'));
        self::assertSame('901821074', Siret::siren('90182107400019'));
        // La Poste: the sum of the digits, a multiple of 5 - not Luhn.
        self::assertTrue(Siret::isValid('35600000000010'));
        self::assertFalse(Siret::isValid('35600000000011'));
        // Its head office, as the register gives it: Luhn-valid, its digits' sum 26.
        self::assertTrue(Siret::isValid('35600000000048'));
    }

    public function testAFrenchVatNumberIsItsSirenWithAKey(): void
    {
        self::assertSame('FR53901821074', Siren::toVatNumber('901821074'));
        self::assertSame('FR53901821074', VatNumber::normalize('901 821 074'), 'a SIREN is enough');
        self::assertSame('FR53901821074', VatNumber::normalize('90182107400019'), 'a SIRET too');
        self::assertTrue(VatNumber::isWellFormed('fr 53 901821074'));
        self::assertFalse(VatNumber::isWellFormed('FR54901821074'), 'the key does not agree with the SIREN');
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
