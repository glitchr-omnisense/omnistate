---
title: Installation
order: 10
---

## Installation

```sh
composer require omniscient/omnistate omnistate/annuaire-entreprises omnistate/vies omnistate/iana
composer require omnistate/matchid omnistate/openarchieven omnistate/national-archives-uk omnistate/nara   # civil registers (nara: with a key)
composer require omnistate/annuaire-sante                                                   # with a key from the ANS
```

### Requirements

- PHP 8.2 or later.
- `omniscient/omnistate` requires `symfony/http-client-contracts` (^3.0) and nothing else.
- Each register requires `omniscient/omnistate` and `symfony/http-client` (^6.4, ^7.0 or ^8.0), a
  library that stands alone and loads no framework.
- `omnistate/iana` suggests `ext-intl`: with it, an international domain name (`café.fr`) is
  turned into its ASCII form (`idn_to_ascii`) before it is asked; without it, such a name is asked
  as it is written.
- A cache is optional: any `Symfony\Contracts\Cache\CacheInterface` (`symfony/cache` provides
  them).

Omnistate needs no framework. It runs the same in plain PHP, in a worker, in Laravel or Slim, and
in Symfony, where a bundle does the wiring ([Symfony](symfony.md)).

### Plain PHP

```php
<?php // bare.php

require __DIR__.'/vendor/autoload.php';

use Omnistate\AnnuaireEntreprises\AnnuaireEntreprises;
use Omnistate\Exception\UnavailableException;
use Omnistate\Iana\Bootstrap;
use Omnistate\Iana\Rdap;
use Omnistate\Omnistate;
use Omnistate\Vies\Vies;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
$omnistate = new Omnistate(
    companies: [new AnnuaireEntreprises($http)],
    vat: [new Vies($http)],
    internet: new Rdap($http, new Bootstrap($http)),
);

$company = $omnistate->company('901 821 074');           // or a SIRET, or FR53901821074
echo $company->name, ', ', $company->legalForm, ', ', $company->status->value, ', created on ', $company->createdOn->format('Y-m-d'), "\n";
echo 'head office ', $company->headOffice->identifier, ', ', $company->headOffice->address, "\n";
echo 'VAT ', $company->vatNumber, "\n\n";

$domain = $omnistate->domain('glitchr.dev');
echo $domain->name, ': ', $domain->registrar->name, ', registered on ', $domain->registeredAt->format('Y-m-d'), ', until ', $domain->expiresAt->format('Y-m-d'), "\n";
echo implode(' ', $domain->nameservers), "\n\n";

try {
    $check = $omnistate->vat($company->vatNumber);
    echo $check->number, $check->valid ? ' is valid: '.$check->name : ' is not valid', "\n";
} catch (UnavailableException $e) {
    // A member state's service has its hours: that is not an answer about the number
    echo 'VIES did not answer (', $e->getMessage(), '): ask again later', "\n";
}
```

```
$ php bare.php
GLITCH ART, SARL, active, created on 2021-07-03
head office 90182107400019, 5 RUE VINCENT SCOTTO 67400 ILLKIRCH-GRAFFENSTADEN
VAT FR53901821074

glitchr.dev: Gandi SAS, registered on 2022-05-19, until 2027-05-19
ns-1-b.gandi.net ns-210-c.gandi.net ns-238-a.gandi.net

FR53901821074 is valid: SARL GLITCH ART
```

(as answered on 2026-10-05, in an empty directory after the first `composer require` above. Run
again on 2026-10-25 in the [harness](harness.md), PHP 8.4: the same company and domain lines,
and France's VIES service refusing calls that day, so the last line read
`VIES did not answer (VIES: MS_MAX_CONCURRENT_REQ.): ask again later`.)

No key, no account: the script asks the State's Recherche d'entreprises API, IANA's RDAP
bootstrap and the registry it names, and the European Commission's VIES - four calls. The pieces:

- a **registry** per source package (`AnnuaireEntreprises`, `Vies`, `Rdap` with its `Bootstrap`,
  `AnnuaireSante`, `MatchId`, `OpenArchieven`, `NationalArchivesUk`, `Nara`), each given the HTTP
  client to call with - the application's, a `MockHttpClient` in a test;
- **`Omnistate\Omnistate`**, built by hand from the registries: `companies`, `vat`, `internet`,
  `professionals`, `civil`, and optionally a cache (`Symfony\Contracts\Cache\CacheInterface`), how
  long answers are kept, and your own VAT number as `requester` (VIES then answers a consultation
  number) - every argument in the [configuration reference](configuration.md#the-facade);
- what it answers: `company()`, `search()`, `vat()`, `domain()`, `network()`, `professional()`,
  `professionals()`, `facility()`, `civilRecords()`, `civilRecord()`, and the civil registers'
  coverage (`civilRegistries()`, `civilRegistry()`, `civilCountries()`) - see
  [Concepts](concepts.md#the-facade).

No class of a framework is loaded on the way - a test of this package checks it in a process of
its own (`Tests/BareTest.php`), and so does `docker compose run --rm omnistate bare`
([harness](harness.md)).

### One registry

Each registry can be used without the facade: it is the contract's methods, nothing more. The
facade adds what is common to all - identifiers normalized, the question routed to the register
that takes it, the cache.

```php
use Omnistate\Vies\Vies;
use Symfony\Component\HttpClient\HttpClient;

$check = (new Vies(HttpClient::create()))->check('FR53901821074', requester: 'FR...');
$check->valid;               // true
$check->consultationNumber;  // the proof you checked it that day
```

Called directly, a registry expects its identifier already normalized (`Vies::check()` takes
`FR53901821074`, not `fr 53 901 821 074`), and some throw an `\InvalidArgumentException` on an
identifier that is not one (`AnnuaireSante::professional('x')`): the facade does both checks
before asking.

### Identifiers, without asking anyone

```php
use Omnistate\Identifier\{Siren, Siret, VatNumber};

Siren::isValid('901821074');               // Luhn
Siren::toVatNumber('901821074');           // FR53901821074
VatNumber::normalize('gr 123456789');      // EL123456789 - each member state's shape
VatNumber::isWellFormed('FR54901821074');  // false: the key disagrees with the SIREN
```

These need `omniscient/omnistate` alone: no source package, no HTTP client. All of them:
[Identifiers](usage-identifiers.md).

### Answers kept a while

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;   // composer require symfony/cache

$omnistate = new Omnistate([new AnnuaireEntreprises($http)], [new Vies($http)], cache: new FilesystemAdapter(), ttl: 86400);
```

Registers change slowly, and some count their calls. An exception is never kept: a register that
did not answer is asked again next time. What is kept and under which key:
[Configuration](configuration.md#the-cache).

### In a framework

- **Symfony**: `Omnistate\Bridge\Symfony\OmnistateBundle` registers every source installed on the
  application's `http_client`, autowires `Omnistate\Omnistate` with a cache pool, and brings the
  `#[Siren]`, `#[Siret]`, `#[VatNumber]`, `#[Rpps]`, `#[Finess]` constraints and a company search
  field: see [Symfony](symfony.md). Its components (`symfony/config`,
  `symfony/dependency-injection`, `symfony/http-kernel`, `symfony/validator`, `symfony/form`,
  `symfony/routing`, `symfony/http-foundation`) are not required by this package: a Symfony
  application has them.
- **Laravel**: there is no bridge package; a service provider builds the facade once:
  [Laravel](laravel.md).
- **Any other**: build `Omnistate` once, where the framework builds its services (a service
  provider, a container definition), as the script above does.

### Errors, in short

| Exception | When |
|---|---|
| `UnavailableException` | the registry did not answer: down, slowed (429, with `$retryAfter`), a key missing or refused, a member state's VIES service closed - **never an answer about the company or the number** |
| `NotSupportedException` | no registry installed knows that kind of identifier, or takes that question |
| `OmnistateException` | a register refused the question itself (matchID's HTTP 400 on a date it cannot read...) |

Unknown is `null` (a company, a domain) or an empty list; a VAT number that does not exist is a
`VatCheck` whose `valid` is `false`. The whole rule: [Errors](errors.md).
