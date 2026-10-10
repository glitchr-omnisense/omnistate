# glitchr/omnistate

What states and registries know, through one contract - the Omnipay of public registers.

```php
$omnistate->company('901 821 074');        // or a SIRET, or FR53901821074
$omnistate->vat('DE123456789');            // valid? whose? (+ a consultation number)
$omnistate->domain('glitchr.dev');         // registrar, dates, name servers
$omnistate->network('193.0.6.139');        // or 'AS3333': who holds it
$omnistate->professional('10003461033');   // a health professional by RPPS: profession, workplaces, MSSanté
$omnistate->facility('580008803');         // a health facility by FINESS
$omnistate->civilRecords(new CivilQuery(familyName: 'Chirac', born: Period::year(1932)));  // acts and documents about a person
```

This package holds the contract, the models (`Company`, `Establishment`, `Manager`, `FinancialYear`,
`VatCheck`, `Domain`, `Network`...), the identifier rules - checked without asking anyone - and a
bridge for Symfony. It needs no framework: it requires nothing but `symfony/http-client-contracts`,
each source package `symfony/http-client`. Each source is a package of its own:

| Package | Source | Access |
|---|---|---|
| `omnistate/annuaire-entreprises` | French companies: the State's Recherche d'entreprises API (INSEE Sirene + INPI's RNE) | free, no key |
| `omnistate/vies` | EU VAT numbers: the European Commission's VIES (REST) | free, no key |
| `omnistate/iana` | Domains, IP ranges, AS numbers: IANA's RDAP bootstrap | free, no key |
| `omnistate/annuaire-sante` | French health professionals (RPPS) and facilities (FINESS): the ANS's FHIR API | free, Gravitee key |
| `omnistate/matchid` | French deaths since 1970: INSEE's file, through matchID | free, no key |
| `omnistate/openarchieven` | Births, marriages, deaths and more from Dutch and Belgian archives, with scans: Open Archives | free, no key |
| `omnistate/national-archives-uk` | Documents about a person in the UK National Archives' catalogue (not civil registration) | free, no key |
| `omnistate/nara` | Federal files about a person in the US National Archives Catalog - pension files, military service, draft cards, naturalization indexes - with links to their scanned pages (not civil registration) | free, API key (10,000 calls a month) |

## Plain PHP

```sh
composer require glitchr/omnistate omnistate/annuaire-entreprises omnistate/vies omnistate/iana
```

```php
require __DIR__.'/vendor/autoload.php';

use Omnistate\AnnuaireEntreprises\AnnuaireEntreprises;
use Omnistate\Iana\{Bootstrap, Rdap};
use Omnistate\Omnistate;
use Omnistate\Vies\Vies;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
$omnistate = new Omnistate(
    companies: [new AnnuaireEntreprises($http)],
    vat: [new Vies($http)],
    internet: new Rdap($http, new Bootstrap($http)),
);

echo $omnistate->company('901 821 074')->name, "\n";            // GLITCH ART
echo $omnistate->domain('glitchr.dev')->registrar->name, "\n";   // Gandi SAS
```

No key: these registries are open. Each registry is given the HTTP client to call with - the
application's, a `MockHttpClient` in a test. The whole script and its answer, and the rest:
[docs/installation.md](docs/installation.md). No class of a framework is loaded on the way:
`Tests/BareTest.php` checks it in a process of its own, and so does
`docker compose run --rm omnistate bare` ([docs/harness.md](docs/harness.md)).

## Identifiers

```php
Siren::isValid('901821074');               // Luhn
Siret::isValid('90182107400019');          // Luhn, La Poste's rule too
Siren::toVatNumber('901821074');           // FR53901821074
VatNumber::normalize('gr 123456789');      // EL123456789 - each member state's shape
VatNumber::isWellFormed('FR54901821074');  // false: the key disagrees with the SIREN
Rpps::isValid('10000000017');              // Luhn; Rpps::normalize() takes the IDNPS (8 + RPPS) too
Finess::isValid('580008803');              // Luhn; 2A / 2B for Corsica
```

Regulated professionals and facilities: [docs/professionals.md](docs/professionals.md).

Civil registers - acts and documents about people, which countries and kinds each one covers:
[docs/civil.md](docs/civil.md).

## Symfony

In a Symfony application a bundle does the wiring; its components (`symfony/config`,
`symfony/dependency-injection`, `symfony/http-kernel`, `symfony/validator`, `symfony/form`,
`symfony/routing`, `symfony/http-foundation`) are not required by this package: the application
has them ([docs/symfony.md](docs/symfony.md)).
`Omnistate\Bridge\Symfony\OmnistateBundle`: `Omnistate\Omnistate` autowired, every `omnistate/*` package
installed registered, answers kept in a cache pool, and the `#[Siren]`, `#[Siret]` and
`#[VatNumber(checkExistence: true)]` constraints.

```yaml
omnistate:
    cache: cache.app          # null: every call asks the registry
    ttl: 86400
    timeout: 10
    requester: FR53901821074  # your VAT number: VIES answers a consultation number
```

A registry that does not answer throws `UnavailableException` (with `$retryAfter` when it said): that is
not an answer about the company or the number.

### Company search

`CompanySearchType` is a search field that suggests companies as one types and, on a pick, fills the
sibling fields named in `fill` (`['vatNumber' => 'vatNumber']`: the French VAT number derived from the
SIREN). It asks `CompanySearchController`, `GET /omnistate/company/search?q=`: route it by importing
`Bridge/Symfony/Controller/` (type `attribute`), or through a subclass in a directory already imported.

**Protect that route.** The controller checks nothing itself: left open, anyone can use your server
as a relay to the registries and spend their rate limits (Recherche d'entreprises: about 7 calls a
second). Put it behind your back office's access control, or give your subclass an `#[IsGranted]`
(base-bundle-market's is `#[IsGranted('MARKET_VIEW')]`).

## Documentation

- [Installation and first calls](docs/installation.md): plain PHP first
- [Symfony](docs/symfony.md)
- [Regulated professionals and facilities](docs/professionals.md)
- [Civil registers](docs/civil.md)
- [The Docker harness](docs/harness.md)

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
