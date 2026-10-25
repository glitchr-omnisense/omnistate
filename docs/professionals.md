---
title: Professionals and facilities
order: 34
---

# Regulated professionals and facilities

`Omnistate\Registry\ProfessionalRegistryInterface` is the one contract for the registers of
regulated professions: France's health professionals (`omnistate/annuaire-sante`, the RPPS),
later its lawyers (a future `omnistate/cnb`, not written). Whoever reads a professional need not
know which profession it is.

```php
$omnistate->professional('10003461033');              // an RPPS number, or its IDNPS form 810003461033
$omnistate->professionals('Martin', postcode: '67');  // by name, practising in that postcode
$omnistate->facility('754567860');                    // a FINESS number
```

## An example, on recorded answers

`omnistate/annuaire-sante` needs a key from the ANS; without one at hand, this script runs the
real register class on the ANS's answers recorded in its `Tests/Fixtures` (fictional people of
the ANS's implementation guide), served by a `MockHttpClient`. With a key, replace the client by
`HttpClient::create()` and `'a-key'` by the key.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnistate\AnnuaireSante\AnnuaireSante;
use Omnistate\Omnistate;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

// The ANS's answers recorded in omnistate/annuaire-sante's Tests/Fixtures, in place of the API
$fixtures = __DIR__.'/vendor/omnistate/annuaire-sante/Tests/Fixtures/';
$http = new MockHttpClient(static fn (string $method, string $url) => new MockResponse((string) file_get_contents($fixtures.match (true) {
    str_contains($url, '/Organization') => 'organization.json',
    str_contains($url, 'identifier=') => 'practitioner-physician.json',
    default => 'search.json',
})));

$omnistate = new Omnistate(professionals: [new AnnuaireSante($http, 'a-key')]);

$professional = $omnistate->professional('810003461033');   // the IDNPS form: the same RPPS
printf("%s %s (%s), %s [%s %s]\n", $professional->prefix, $professional->name(), $professional->identifier, $professional->profession, $professional->profession?->system, $professional->profession?->code);
foreach ($professional->specialties as $specialty) {
    printf("  specialty %s (%s %s)\n", $specialty, $specialty->system, $specialty->code);
}
foreach ($professional->diplomas as $diploma) {
    printf("  diploma %s (%s)\n", $diploma, $diploma->code);
}
foreach ($professional->workplaces as $workplace) {
    printf("  works at %s, FINESS %s, %s, %s, %s\n", $workplace->name, $workplace->facility, $workplace->mode, $workplace->role, $workplace->active ? 'active' : 'ended');
}
echo '  secure e-mails: ', implode(', ', $professional->secureEmails) ?: 'none', "\n";

$facility = $omnistate->facility('754 567 860');
printf("\n%s (%s, %s), %s\n", $facility->name, $facility->identifier, $facility->kind, $facility->address);
foreach ($facility->types as $type) {
    printf("  type %s (%s)\n", $type, $type->code);
}

echo "\nSearch \"a\":\n";
foreach ($omnistate->professionals('a', limit: 5) as $found) {
    printf("  %s %-22s %s\n", $found->identifier, $found->name(), $found->profession);
}
```

```
M Arthur Saucier (10003461033), Médecin [TRE_G15-ProfessionSante 10]
  diploma DE05 (DE05)
  works at CABINET SAINT ANTOINE, FINESS 548812, liberal, FON-AU, active
  secure e-mails: none

CH EURE-SEINE (754567860, site), 96 R DIDOT 75014 PARIS
  type SA01 (SA01)
  type 86.10Z (86.10Z)

Search "a":
  10003461033 Arthur Saucier         Médecin
  10102727017 David CHATELIER        Pharmacien
```

(run on 2026-10-25 in the [harness](harness.md), on the recorded answers - not against the ANS)

The workplace's `facility` is `548812` here: the recorded organization has no FINESS, and the
register's structure id stands in for it.

## A professional

The identifier loses its spaces, dots and dashes, and an IDNPS (`8` + RPPS, what Pro Santé Connect
and the Annuaire Santé call it) becomes its RPPS. The facade asks the first professional register
whose `supports()` takes it - `omnistate/annuaire-sante` takes eleven digits, without checking the
Luhn key (`Rpps::isValid()` does, and the `#[Rpps]` constraint). Anything else is a
`NotSupportedException`, before any request; a professional the register does not know is `null`.

## By name

`professionals($name, $postcode, $limit)` asks each professional register in turn and answers the
first non-empty list. With a postcode, only those with a workplace whose postal code starts with
it are kept (`'67'` keeps the whole Bas-Rhin): `omnistate/annuaire-sante` then asks for 50
practitioners and filters them, so a common name in a small place may find none of those it
would have found among more. An empty name is `[]`.

## A facility

`facility()` normalizes a FINESS number (`Finess::normalize()`: nine characters, 2A / 2B for
Corsica) and asks the first professional register that implements `FacilityRegistryInterface`
too and whose `supportsFacility()` takes it.

## The models

| Model | What it holds |
|---|---|
| `Professional` | `identifier`, `familyName`, `givenName`, `prefix`, `profession` and `specialties` and `diplomas` (`Qualification`: code, nomenclature, label), `workplaces`, `phones`, `emails`, `secureEmails` (MSSanté), `languages`, `active`, `updatedAt`, `raw` |
| `Workplace` | where and how one practises: `name`, `address`, `facility` (its FINESS), `mode` (`liberal`, `salaried`, `volunteer`), `role`, `phones`, `secureEmails`, `active` |
| `Facility` | `identifier` (FINESS), `name`, `kind` (`legal` entity or `site`), `types`, `address`, `phones`, `emails`, `secureEmails`, `identifiers`, `active` |

Every property, its type and default: [Concepts](concepts.md#professional-workplace-facility-qualification).
How the ANS's FHIR resources are read: [omnistate/annuaire-sante](https://github.com/glitchr-studio/omnistate-annuaire-sante).

A register that knows facilities too implements `FacilityRegistryInterface` (`supportsFacility()`,
`facility()`); `Omnistate::facility()` asks the professional registries that do.

## Identifiers, checked without asking anyone

```php
Rpps::isValid('10003461033');      // eleven digits, Luhn key
Rpps::normalize('810003461033');   // "10003461033": the IDNPS is "8" + RPPS
Rpps::toIdnps('10003461033');      // "810003461033", what Pro Santé Connect and the Annuaire Santé call it
Rpps::withKey('1000000001');       // "10000000017": a valid fictional number, for fixtures
Finess::isValid('580008803');      // nine characters, Luhn key; 2A / 2B for Corsica
```

The `#[Rpps]` and `#[Finess]` constraints check the same ([Constraints](symfony.md#constraints)).

## Symfony

```yaml
omnistate:
    annuaire_sante:
        api_key: '%env(ESANTE_API_KEY)%'   # empty: every call throws UnavailableException
```

Registries of your own implementing `ProfessionalRegistryInterface` are tagged
`omnistate.professional_registry` and asked in turn.

A registry that does not answer throws `UnavailableException`: that is not "no such professional".
Without a key, `omnistate/annuaire-sante` throws it on every call, before any request.
