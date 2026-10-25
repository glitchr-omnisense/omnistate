---
title: Symfony
order: 15
---

# Symfony

Omnistate runs without a framework ([installation](installation.md)); in a Symfony application its
bundle does the wiring. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel`, and `symfony/validator`, `symfony/form`, `symfony/routing`,
`symfony/http-foundation` for the constraints and the company search - are not required by
`omniscient/omnistate`: the application has them, and nothing of them is loaded outside Symfony.

## Registering the bundle

There is no Flex recipe: add the bundle by hand.

```php
// config/bundles.php
return [
    // ...
    Omnistate\Bridge\Symfony\OmnistateBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/omnistate.yaml
omnistate:
    cache: cache.app          # null: every call asks the registry
    ttl: 86400
    timeout: 10
    requester: FR53901821074  # your VAT number: VIES answers a consultation number
    matchid:
        token: ~              # raises matchID's quota
    nara:
        api_key: '%env(NARA_API_KEY)%'     # the Catalog API key, 10,000 calls a month: the cache (ttl) spares it
    annuaire_sante:
        api_key: '%env(ESANTE_API_KEY)%'   # the Gravitee key; empty: every call answers UnavailableException
```

```sh
# .env.local, or bin/console secrets:set
NARA_API_KEY=...
ESANTE_API_KEY=...
```

The whole tree, from `OmnistateBundle::configure()` (dumped with Symfony's reference dumper in the
[harness](harness.md) on 2026-10-25):

| Key | Type | Default | |
|---|---|---|---|
| `omnistate.cache` | string or null | `cache.app` | a cache pool's service id; `null` (`~`): no cache, every call asks the register |
| `omnistate.ttl` | integer, at least 0 | `86400` | seconds an answer is kept in that pool |
| `omnistate.timeout` | float | `10` | seconds each register (and IANA's bootstrap) has to answer, handed to every register's constructor |
| `omnistate.requester` | string or null | `null` | your own VAT number, sent with every VAT check that names no other: VIES then answers a consultation number |
| `omnistate.matchid.token` | string or null | `null` | a matchID API token (`Authorization: Bearer`); optional, a higher quota |
| `omnistate.nara.api_key` | string or null | `null` | the Catalog API key (`x-api-key`); without one, every NARA call is an `UnavailableException` |
| `omnistate.annuaire_sante.api_key` | string or null | `null` | the Gravitee key (`ESANTE-API-KEY`); without one, every Annuaire Santé call is an `UnavailableException`, before any request |
| `omnistate.annuaire_sante.url` | string | `https://gateway.api.esante.gouv.fr/fhir/v2` | the FHIR API's base address |

Nothing else is configurable through the bundle: `OpenArchieven`'s label language (`lang`, `en`)
and the other registers' base addresses keep their defaults. An application that needs another
value defines the register's service itself ([An application's own
registers](#an-applications-own-registers)).

## Services

The bundle registers each register whose class exists - the packages installed - on the
application's `http_client` service, with `omnistate.timeout`:

| Service id | Built with | Tag |
|---|---|---|
| `Omnistate\AnnuaireEntreprises\AnnuaireEntreprises` | `http_client`, timeout | `omnistate.company_registry` |
| `Omnistate\Vies\Vies` | `http_client`, timeout | `omnistate.vat_registry` |
| `Omnistate\AnnuaireSante\AnnuaireSante` | `http_client`, `annuaire_sante.api_key`, timeout, `annuaire_sante.url` | `omnistate.professional_registry` |
| `Omnistate\MatchId\MatchId` | `http_client`, `matchid.token`, timeout | `omnistate.civil_registry` |
| `Omnistate\OpenArchieven\OpenArchieven` | `http_client`, timeout | `omnistate.civil_registry` |
| `Omnistate\NationalArchivesUk\NationalArchivesUk` | `http_client`, timeout | `omnistate.civil_registry` |
| `Omnistate\Nara\Nara` | `http_client`, `nara.api_key`, timeout | `omnistate.civil_registry` |
| `Omnistate\Iana\Bootstrap` | `http_client`, the cache pool (or `null`), timeout | - |
| `Omnistate\Iana\Rdap` | `http_client`, `Bootstrap`, timeout; aliased as `Omnistate\Registry\InternetRegistryInterface` | - |

And the bundle's own:

| Service id | Public | |
|---|---|---|
| `Omnistate\Omnistate` | yes | the facade: the tagged registers by kind, `Rdap` as the Internet registry when `omnistate/iana` is installed, the cache pool, `ttl`, `requester`. Autowired by its class. |
| `Omnistate\Bridge\Symfony\CompanySearch` | yes | the company search's rows ([Company search](#company-search)) |
| `Omnistate\Bridge\Symfony\Controller\CompanySearchController` | yes | its endpoint, tagged `controller.service_arguments` |
| `Omnistate\Bridge\Symfony\Form\CompanySearchType` | no | the form field, tagged `form.type`, given the `router` when there is one; registered only when `symfony/form` is installed |
| `Omnistate\Bridge\Symfony\Validator\VatNumberValidator` | no | tagged `validator.constraint_validator`, given the facade (for `checkExistence`) |

```php
use Omnistate\Omnistate;

final class SupplierController
{
    public function __construct(private readonly Omnistate $omnistate)
    {
    }
}
```

Nothing is asked when the container compiles: a register is called the first time a question
needs it. `Omnistate\Iana\Bootstrap` keeps IANA's bootstrap files a day in the same cache pool
(`omnistate.iana.bootstrap.dns`, `.ipv4`, `.ipv6`, `.asn`).

When Twig is installed, the bundle prepends its form theme
`@Omnistate/form/company_search.html.twig` to `twig.form_themes`: the company search field renders
with its suggestions list and script in every form.

The bundle registers no command, no event, no Twig function, and no route of its own: the
company search's route exists only once the application imports it.

## An application's own registers

A class of the application that implements one of these contracts is autoconfigured - tagged -
and the facade asks it too. The facade asks the registers of a kind in the order of the tagged
iterator (Symfony's `tagged_iterator()`: by the tag's priority, then by definition), and for a
lookup the first whose `supports()` accepts the identifier answers. The bundle sets no priority on
the packages' registers.

| Contract | Tag | Asked by |
|---|---|---|
| `Omnistate\Registry\CompanyRegistryInterface` | `omnistate.company_registry` | `company()`, `search()` |
| `Omnistate\Registry\VatRegistryInterface` | `omnistate.vat_registry` | `vat()` |
| `Omnistate\Registry\ProfessionalRegistryInterface` | `omnistate.professional_registry` | `professional()`, `professionals()`, and `facility()` when it implements `FacilityRegistryInterface` too |
| `Omnistate\Registry\CivilRegistryInterface` | `omnistate.civil_registry` | `civilRecords()`, `civilRecord()`, `civilRegistries()`... |

Two contracts are not autoconfigured: `FacilityRegistryInterface` alone (the facade asks facilities
only of its professional registers, so a facility register must implement
`ProfessionalRegistryInterface` too), and `InternetRegistryInterface` (the facade takes one
Internet registry, `Rdap` when `omnistate/iana` is installed; another one is given by redefining
the `Omnistate\Omnistate` service's third argument).

A register of a package with another configuration than the bundle's - `OpenArchieven` labelled
in Dutch, say - is defined by the application under its class name; the application's definition
wins over the bundle's (Symfony keeps the application's definitions over an extension's):

```yaml
# config/services.yaml
services:
    Omnistate\OpenArchieven\OpenArchieven:
        arguments: ['@http_client', 10, 'nl']
        tags: [omnistate.civil_registry]
```

## Constraints

Five constraints in `Omnistate\Bridge\Symfony\Validator`, each an attribute on a property or a
method, repeatable, with its own `message` (and `groups`, `payload`). A `null` or `''` value
passes: combine them with `#[NotBlank]` when the field is required.

| Constraint | Checks | Default message |
|---|---|---|
| `#[Siren]` | nine digits, the Luhn key right (`Identifier\Siren::isValid()`) | `This is not a valid SIREN.` |
| `#[Siret]` | fourteen digits, the key right, La Poste's rule too (`Identifier\Siret::isValid()`) | `This is not a valid SIRET.` |
| `#[VatNumber]` | the member state's shape, and for France the key agreeing with the SIREN (`Identifier\VatNumber::isWellFormed()`) | `This is not a valid EU VAT number.` |
| `#[VatNumber(checkExistence: true)]` | the same, then VIES through the facade: a number VIES says does not exist gets `unknownMessage` | `No EU member state knows this VAT number.` |
| `#[Rpps]` | eleven digits (or the IDNPS, 8 + RPPS), the Luhn key right | `This is not a valid RPPS number.` |
| `#[Finess]` | nine characters (2A / 2B for Corsica), the Luhn key right | `This is not a valid FINESS number.` |

```php
use Omnistate\Bridge\Symfony\Validator as Omnistate;

final class SupplierForm
{
    #[Omnistate\Siren]
    public ?string $siren = null;

    #[Omnistate\Siret(message: 'Ce SIRET est mal écrit.')]
    public ?string $siret = null;

    #[Omnistate\VatNumber(checkExistence: true)]   // its shape, then VIES
    public ?string $vatNumber = null;

    #[Omnistate\Rpps]
    public ?string $rpps = null;

    #[Omnistate\Finess]
    public ?string $finess = null;
}
```

With `checkExistence`, VIES not answering is not a reason to refuse the number: on any
`OmnistateException` (an `UnavailableException`, or no VAT register installed) the value passes.
Without the bundle (a validator built by hand) `VatNumberValidator` has no facade, and
`checkExistence` checks the shape only. The `{{ value }}` parameter is set on every violation.

`#[VatNumber]` reads the value through `VatNumber::normalize()`, which turns a bare SIREN or SIRET
into its French VAT number: `901821074` in a VAT number field passes (and is checked with VIES as
`FR53901821074`). Store the normalized number, not what was typed, when that matters.

## Company search

`CompanySearchType` is a search field that suggests companies as one types and, on a pick, fills
the sibling fields named in `fill`:

```php
use Omnistate\Bridge\Symfony\Form\CompanySearchType;

$builder
    ->add('company', CompanySearchType::class, [
        'fill' => ['vatNumber' => 'vatNumber', 'companyName' => 'name', 'address' => 'address', 'siret' => 'siret'],
    ])
    ->add('companyName')
    ->add('siret')
    ->add('vatNumber')
    ->add('address');
```

| Option | Type | Default | |
|---|---|---|---|
| `fill` | array | `[]` | sibling field name => result key; siblings the form does not have are left out |
| `endpoint` | string or null | `null` | the address asked; `null`: the route `omnistate_company_search`, or `/omnistate/company/search` without a router |
| `min_length` | int | `3` (`CompanySearch::MIN_LENGTH`) | characters typed before the first request |
| `limit` | int | `10` | suggestions asked for |
| `mapped` | bool | `false` | the field only helps fill the others |
| `required` | bool | `false` | |

Its parent is `SearchType`, its block prefix `omnistate_company_search`. The view's `attr` carries
`autocomplete="off"`, `data-omnistate-company-search` (the endpoint), `data-omnistate-fill` (a JSON
object: the siblings' element ids, `<form id>_<field>`, to result keys),
`data-omnistate-min-length` and `data-omnistate-limit`. The form theme's script, bound once on the
document, waits 300 ms after the last key, asks the endpoint with `q` and `limit`, lists the rows
(a closed company dimmed, marked `(closed)`), and on a pick writes the name in the field and each
key's value in its sibling, dispatching `input` and `change` on it; Escape closes the list.

The result keys, from `CompanySearch::row()`:

| Key | Value |
|---|---|
| `siren` | the company's identifier |
| `siret` | the head office's identifier, or `null` |
| `name` | its legal name, else its name |
| `vatNumber` | the French VAT number derived from the SIREN (for a French company with a valid SIREN), else `null` - VIES still has the last word on it |
| `address`, `postalCode`, `city` | the head office's address, one line, and its parts |
| `activity` | the activity's code and label (`62.02A`) |
| `legalForm` | its short name, else its label, else its code (`SARL`) |
| `active` | `true` unless the company is closed |
| `country` | ISO 3166-1 alpha-2 |

The field asks `CompanySearchController`, `GET /omnistate/company/search?q=...&limit=...` (route
name `omnistate_company_search`). Route it by importing the bridge's controllers:

```yaml
# config/routes/omnistate.yaml
omnistate:
    resource: '../../vendor/omniscient/omnistate/Bridge/Symfony/Controller/'
    type: attribute
```

or through a subclass of `CompanySearchController` in a directory already imported. It answers
`{"results": [...]}` (200, `Cache-Control: private, max-age=3600`), at most 25 rows
(`CompanySearch::MAX_RESULTS`), none for a query shorter than three characters (no register
asked); when the registers do not answer (any `OmnistateException`), `{"results": [], "error":
"unavailable"}` with a 503. Rows come from the facade's `search()`: the first company register
that finds anything, from the cache when it was asked before.

> [!WARNING]
> **Protect that route.** The controller checks nothing itself: left open, anyone can use your
> server as a relay to the registries and spend their rate limits (Recherche d'entreprises: about
> 7 calls a second). Put it behind your back office's access control, or give your subclass an
> `#[IsGranted]`.
