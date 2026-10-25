---
title: Configuration reference
order: 40
---

## Configuration reference

Omnistate has no configuration file of its own: the facade and each register take their settings
as constructor arguments. In Symfony, the bundle's tree sets them ([Symfony](symfony.md#configuration)).

### The facade

`new Omnistate\Omnistate(...)`, every argument optional and best given by name:

| Argument | Type | Default | |
|---|---|---|---|
| `companies` | `iterable<CompanyRegistryInterface>` | `[]` | the company registers, asked in this order |
| `vat` | `iterable<VatRegistryInterface>` | `[]` | the VAT registers |
| `internet` | `?InternetRegistryInterface` | `null` | the one Internet registry (`Iana\Rdap`); `null`: `domain()` and `network()` throw `NotSupportedException` |
| `cache` | `?Symfony\Contracts\Cache\CacheInterface` | `null` | where answers are kept; `null`: every call asks the register |
| `ttl` | `int` | `86400` | seconds an answer is kept |
| `requester` | `?string` | `null` | your own VAT number, sent with every check that names no other: VIES then answers a consultation number |
| `professionals` | `iterable<ProfessionalRegistryInterface>` | `[]` | the professional registers; those implementing `FacilityRegistryInterface` answer `facility()` too |
| `civil` | `iterable<CivilRegistryInterface>` | `[]` | the civil registers, asked in this order |

The iterables are read once, when the facade is built (a generator is consumed then).

### The cache

With a cache, every question that reaches a register is kept `ttl` seconds under
`omnistate.<xxh128 hash>` of a key made of what was asked:

| Method | Key, before hashing |
|---|---|
| `company()` | `company.<register>.<normalized identifier>` |
| `search()` | `search.<register>.<limit>.<query, trimmed, lower case>` |
| `vat()` | `vat.<register>.<normalized number>.<normalized requester>` |
| `professional()` | `professional.<register>.<normalized identifier>` |
| `professionals()` | `professionals.<register>.<limit>.<postcode>.<name, trimmed, lower case>` |
| `facility()` | `facility.<register>.<normalized identifier>` |
| `civilRecords()` | `civil.search.<register>.<CivilQuery::key()>` |
| `civilRecord()` | `civil.record.<register>.<identifier>` |
| `domain()` | `domain.<normalized name>` |
| `network()` | `network.<normalized address>` |

What is kept is the register's answer, whatever it is: a `null` (not known) and an empty list are
kept too - a company created this morning stays unknown until the entry expires. An exception is
never kept: a register that did not answer is asked again next time. `search()` and
`professionals()` keep each register's answer separately, the empty ones included, while they
look for the first that finds something.

`Iana\Bootstrap` takes a cache of its own (often the same pool) for IANA's bootstrap files, kept
86400 seconds under `omnistate.iana.bootstrap.<dns|ipv4|ipv6|asn>`, whatever the facade's `ttl`.

### The registers

Every register takes the HTTP client to call with first - any
`Symfony\Contracts\HttpClient\HttpClientInterface`: `HttpClient::create()`, the application's, a
`MockHttpClient` in a test - and a timeout in seconds, applied to each request.

| Class | Constructor | Defaults |
|---|---|---|
| `AnnuaireEntreprises\AnnuaireEntreprises` | `(HttpClientInterface $http, float $timeout = 10)` | `URL = https://recherche-entreprises.api.gouv.fr/search` (a constant, not an argument) |
| `Vies\Vies` | `(HttpClientInterface $http, float $timeout = 10)` | `URL = https://ec.europa.eu/taxation_customs/vies/rest-api/check-vat-number` (a constant) |
| `Iana\Bootstrap` | `(HttpClientInterface $http, ?CacheInterface $cache = null, float $timeout = 10)` | `URL = https://data.iana.org/rdap/%s.json` (a constant) |
| `Iana\Rdap` | `(HttpClientInterface $http, Bootstrap $bootstrap, float $timeout = 10)` | the server IANA's bootstrap names |
| `AnnuaireSante\AnnuaireSante` | `(HttpClientInterface $http, ?string $apiKey = null, float $timeout = 10, string $url = self::URL)` | `URL = https://gateway.api.esante.gouv.fr/fhir/v2`; no key: every call is an `UnavailableException` |
| `MatchId\MatchId` | `(HttpClientInterface $http, ?string $token = null, float $timeout = 10, string $url = self::URL)` | `URL = https://deces.matchid.io/deces/api/v1`; the token optional |
| `OpenArchieven\OpenArchieven` | `(HttpClientInterface $http, float $timeout = 10, string $lang = 'en', string $url = self::URL)` | `URL = https://api.openarchieven.nl/1.1`; `lang`: `en`, `nl`, `de` or `fr`, the language of the labels |
| `NationalArchivesUk\NationalArchivesUk` | `(HttpClientInterface $http, float $timeout = 10, string $url = self::URL)` | `URL = https://discovery.nationalarchives.gov.uk/API` |
| `Nara\Nara` | `(HttpClientInterface $http, ?string $apiKey = null, float $timeout = 10, string $url = self::URL)` | `URL = https://catalog.archives.gov/api/v2`; no key: the Catalog answers its web page, an `UnavailableException` |

The `url` arguments exist for a proxy or a test server; none of these services has a sandbox
address. What each register does with its settings: its own manual
([Registers](index.md#registers)).

### The Symfony bundle

| Key | Sets |
|---|---|
| `omnistate.cache` | the facade's `cache` and `Bootstrap`'s `cache` (a pool's service id, `cache.app` by default) |
| `omnistate.ttl` | the facade's `ttl` |
| `omnistate.timeout` | every register's `timeout` |
| `omnistate.requester` | the facade's `requester` |
| `omnistate.annuaire_sante.api_key`, `.url` | `AnnuaireSante`'s `apiKey` and `url` |
| `omnistate.matchid.token` | `MatchId`'s `token` |
| `omnistate.nara.api_key` | `Nara`'s `apiKey` |

Types and defaults: [Symfony](symfony.md#configuration).
