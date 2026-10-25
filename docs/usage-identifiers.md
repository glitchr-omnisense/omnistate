---
title: Identifiers
order: 36
---

# Identifiers

`Omnistate\Identifier\` checks identifiers without asking anyone: their shape and their check
digit. A well-written identifier can still belong to no one - only a register says that. These
classes need `omniscient/omnistate` alone (no register, no HTTP client); the facade and the
registers use them before any request, and the Symfony constraints are built on them.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnistate\Identifier\CountryCode;
use Omnistate\Identifier\Finess;
use Omnistate\Identifier\Luhn;
use Omnistate\Identifier\Rpps;
use Omnistate\Identifier\Siren;
use Omnistate\Identifier\Siret;
use Omnistate\Identifier\VatNumber;

$show = static fn (string $call, mixed $value) => printf("%-40s %s\n", $call, var_export($value, true));

$show("Siren::normalize('901 821 074')", Siren::normalize('901 821 074'));
$show("Siren::isValid('901821075')", Siren::isValid('901821075'));
$show("Siren::toVatNumber('356000000')", Siren::toVatNumber('356000000'));
$show("Siret::isValid('35600000000048')", Siret::isValid('35600000000048'));
$show("Siret::siren('901 821 074 00019')", Siret::siren('901 821 074 00019'));
$show("VatNumber::normalize('gr 123456789')", VatNumber::normalize('gr 123456789'));
$show("VatNumber::normalize('90182107400019')", VatNumber::normalize('90182107400019'));
$show("VatNumber::normalize('DE12345')", VatNumber::normalize('DE12345'));
$show("VatNumber::isWellFormed('FR54901821074')", VatNumber::isWellFormed('FR54901821074'));
$show("VatNumber::country('EL123456789')", VatNumber::country('EL123456789'));
$show("Rpps::normalize('810003461033')", Rpps::normalize('810003461033'));
$show("Rpps::toIdnps('10003461033')", Rpps::toIdnps('10003461033'));
$show("Rpps::withKey('1000000001')", Rpps::withKey('1000000001'));
$show("Finess::isValid('2A0000010')", Finess::isValid('2A0000010'));
$show("Finess::department('67 000 123 4')", Finess::department('67 000 123 4'));
$show("Luhn::isValid('79927398713')", Luhn::isValid('79927398713'));
$show("CountryCode::alpha2('FRA')", CountryCode::alpha2('FRA'));
$show("CountryCode::alpha3('nl')", CountryCode::alpha3('nl'));
$show("CountryCode::isValid('UK')", CountryCode::isValid('UK'));
```

```
Siren::normalize('901 821 074')          '901821074'
Siren::isValid('901821075')              false
Siren::toVatNumber('356000000')          'FR39356000000'
Siret::isValid('35600000000048')         false
Siret::siren('901 821 074 00019')        '901821074'
VatNumber::normalize('gr 123456789')     'EL123456789'
VatNumber::normalize('90182107400019')   'FR53901821074'
VatNumber::normalize('DE12345')          NULL
VatNumber::isWellFormed('FR54901821074') false
VatNumber::country('EL123456789')        'EL'
Rpps::normalize('810003461033')          '10003461033'
Rpps::toIdnps('10003461033')             '810003461033'
Rpps::withKey('1000000001')              '10000000017'
Finess::isValid('2A0000010')             false
Finess::department('67 000 123 4')       '67'
Luhn::isValid('79927398713')             true
CountryCode::alpha2('FRA')               'FR'
CountryCode::alpha3('nl')                'NLD'
CountryCode::isValid('UK')               false
```

(run on 2026-10-25 in the [harness](harness.md); no network)

Every `normalize()` removes spaces, dots and dashes first, and answers `null` when what is left
does not have the identifier's shape.

## Siren

A French company's SIREN: nine digits, the last a Luhn check digit.

| Method | |
|---|---|
| `normalize(?string): ?string` | nine digits, or `null` |
| `isValid(?string): bool` | normalized, and the Luhn key right |
| `toVatNumber(string): string` | the French intra-EU VAT number: `FR` + `(12 + 3 × (SIREN mod 97)) mod 97` on two digits + the SIREN; `\InvalidArgumentException` when it is not nine digits |

## Siret

A French establishment's SIRET: the company's SIREN and a five-digit NIC.

| Method | |
|---|---|
| `normalize(?string): ?string` | fourteen digits, or `null` |
| `isValid(?string): bool` | its SIREN valid, and the fourteen digits pass the Luhn check - except La Poste's (SIREN `356000000`): for a NIC other than `00012`, the sum of the digits must be a multiple of 5 |
| `siren(string): string` | its first nine digits; `\InvalidArgumentException` when it is not fourteen digits |

> [!WARNING]
> The register writes La Poste's head office `35600000000048`: it passes the Luhn check but not
> the sum rule, and `isValid()` refuses it (see the run above). Listed in
> [Verified, and not](verified.md).

## VatNumber

Intra-EU VAT numbers as VIES writes them: a country prefix and the number in each country's shape
(`VatNumber::FORMATS`, a regular expression per prefix).

| Prefix | Shape after it | Prefix | Shape after it |
|---|---|---|---|
| `AT` | `U` + 8 digits | `IT` | 11 digits |
| `BE` | 0 or 1 + 9 digits | `LT` | 9 or 12 digits |
| `BG` | 9 or 10 digits | `LU` | 8 digits |
| `CY` | 8 digits + a letter | `LV` | 11 digits |
| `CZ` | 8 to 10 digits | `MT` | 8 digits |
| `DE` | 9 digits | `NL` | 9 digits + `B` + 2 digits |
| `DK` | 8 digits | `PL` | 10 digits |
| `EE` | 9 digits | `PT` | 9 digits |
| `EL` (Greece) | 9 digits | `RO` | 2 to 10 digits |
| `ES` | a letter or digit + 7 digits + a letter or digit | `SE` | 12 digits |
| `FI` | 8 digits | `SI` | 8 digits |
| `FR` | 2 characters (digits or letters but I and O) + 9 digits | `SK` | 10 digits |
| `HR` | 11 digits | `XI` (Northern Ireland) | 9 or 12 digits, `GD` + 3 digits, `HA` + 3 digits |
| `HU` | 8 digits | | |
| `IE` | 7 digits + a letter A-W + an optional letter A-I, or a digit + a letter, `+` or `*` + 5 digits + a letter A-W | | |

| Method | |
|---|---|
| `normalize(?string): ?string` | upper case; a SIREN or a SIRET becomes its French number; `GR` becomes `EL`; `null` when the prefix is not one of the table's or the rest does not have its shape |
| `isWellFormed(?string): bool` | normalized, and for a French number with a numeric key, the key agreeing with the SIREN (`Siren::toVatNumber()`) |
| `country(string): string` | the first two letters |

## Rpps

A French health professional's RPPS number: eleven digits, the last a Luhn check digit. Its
national form, the IDNPS, is `8` followed by the RPPS.

| Method | |
|---|---|
| `normalize(?string): ?string` | eleven digits; a twelve-digit IDNPS gives its RPPS; else `null` |
| `isValid(?string): bool` | normalized, and the Luhn key right |
| `toIdnps(string): string` | `8` + the RPPS; `\InvalidArgumentException` when it is not one |
| `withKey(string $tenDigits): string` | ten digits with their Luhn key appended - a valid fictional number for fixtures; `\InvalidArgumentException` without ten digits |

## Finess

A French health or social facility's FINESS number: nine characters, the département first (two
digits, or `2A` / `2B` for Corsica), the last a Luhn check digit over the eight before it. A legal
entity (FINESS juridique) and each of its sites (FINESS géographique) have one each.

| Method | |
|---|---|
| `normalize(?string): ?string` | upper case, nine characters of that shape, or `null` |
| `isValid(?string): bool` | normalized, and the Luhn check right, `A` counting as 0 and `B` as 1 |
| `department(string): ?string` | the first two characters: `67`, `2A` |

## Luhn and CountryCode

`Luhn::isValid(string $digits): bool` is the Luhn check (ISO/IEC 7812): every second digit from
the right doubled; `false` for anything but digits.

`CountryCode` keeps ISO 3166-1 codes the way Omnistate does, with two letters: `ALPHA3` maps the
249 alpha-3 codes officially assigned to their alpha-2 codes. `alpha2(?string)` takes either form
(`FRA`, `fr`) and answers `null` for anything else (`UK` is not an ISO code: Great Britain is
`GB`); `alpha3(?string)` the other way; `isValid(?string)`.
