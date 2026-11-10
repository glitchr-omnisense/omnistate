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

## Statistics and policies (2026-11-10)

Run on 2026-11-10 in the harness (PHP 8.4.27, PHPUnit 11.5.57) on omnisense/contracts' published
1.x (5bac28b) and the eleven registers' checkouts: `OK (241 tests)`, PHPStan at level max on the
twelve packages with no error beyond the baselines of the eight older registers and the core
(their findings of 2026-11-10, before this work; the three statistics sources' baselines empty).
`bare` run live: the company, the domain and the IRL answered (`2026-Q3: 148.06, published on
2026-10-16`), VIES refused with `MS_MAX_CONCURRENT_REQ` again.

Read and called live on 2026-11-10, for the statistics sources' fixtures (about twenty calls, a
User-Agent naming the package):
- INSEE's BDM: the IRL, ICC, ILC, ILAT and the consumer price index (base 2025, found through the
  IPC-2025 dataflow), an IPC series with history, an unknown idBank (404), the dataflow list, four
  code lists (OBS_STATUS, OBS_QUAL, OBS_TYPE, OBS_REV); the portal's catalogue, plan (KEY_LESS, 100 a
  minute) and CGU (30 a minute) through its API;
- Eurostat: the HICP's annual rate for France, GDP with provisional values, an unknown dataset (404),
  an unknown code (an empty dataset), the table of contents; the API guide and the copyright notice;
- the ECB: the US dollar's reference rate, the main refinancing rate, an unknown key (404), the
  dataflow list (only with `detail=allstubs`: without it the gateway answered 504 twice), a series
  with `includeHistory=true` (an empty body: history not supported); its API pages and copyright.

Not verified: INSEE's and the ECB's 429 (no limit reached), Eurostat's asynchronous answer (its
JSON is the guide's, its HTTP status not seen), an ECB series with a provisional or confidential
value, the bundle's statistics services in a Symfony application. The eight older registers'
policies carry their manuals' figures; their terms were not read again.

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
