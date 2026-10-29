---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-25 in the [harness](harness.md) image (PHP 8.4.26, PHPUnit 11.5), on the workspace
that installs the core and the eight registers from their checkouts: `OK (124 tests, 718
assertions)`. All offline:

- the core: the facade's routing by kind of identifier and its cache (`Tests/OmnistateTest.php`),
  the identifiers (`Tests/Identifier/IdentifierTest.php`), professionals and civil records through
  the facade (`Tests/ProfessionalTest.php`, `Tests/CivilTest.php`), the bundle's wiring and
  constraints (`Tests/Bridge/OmnistateBundleTest.php`), the company search, its endpoint and its
  field (`Tests/Bridge/CompanySearchTest.php`), and no class of a framework loaded in plain PHP
  (`Tests/BareTest.php`, which runs `bare --recorded --json` in a process of its own);
- each register: its own tests on the service's recorded answers (`Tests/Fixtures`), and the
  conformance suite `RegistryContractTests` ([Testing](testing.md#the-conformance-suite)), five
  tests each.

## What was run live

On 2026-10-25, from the harness, against the services (every example of this manual marked
"run on 2026-10-25" pastes that output):

- Recherche d'entreprises: a company by SIREN, by SIRET, a search by name;
- VIES: five numbers (two valid, three refused for their shape without a call), and France's
  service answering `MS_MAX_CONCURRENT_REQ` for another number minutes earlier;
- IANA's RDAP bootstrap, then Verisign's and the RIPE NCC's RDAP servers: a domain, an IP
  address, an AS number;
- matchID, Open Archives and Discovery: the [civil registers' example](civil.md#an-example-live).

The registers' manuals state their own earlier live runs (matchID, Open Archives and Discovery on
2026-10-04, NARA with a key on 2026-10-10).

## Not verified

- The Annuaire Santé was not called: no key at hand. Its examples run on the ANS's recorded
  answers.
- NARA was not called on 2026-10-25: no key at hand.
- The [Laravel](laravel.md) page was not run in a Laravel application.
- The Symfony bundle was not run in a Symfony application here: its tree and its services were
  read from a container built by hand in the harness (`OmnistateBundle`'s extension loaded and
  compiled), and its tests run in the suite above.

## Defects found in the code

None left: those the manuals found were fixed on 2026-10-29.

In the documentation, corrected here: `docs/civil.md` said there was no NARA package "until a key
is at hand to verify one", while `omnistate/nara` exists and the bundle registers it.
