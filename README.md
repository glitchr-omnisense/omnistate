# glitchr/omnistate

What states and registries know, through one contract - the Omnipay of public registers.

```php
$omnistate->company('901 821 074');        // or a SIRET, or FR53901821074
$omnistate->vat('DE123456789');            // valid? whose? (+ a consultation number)
$omnistate->domain('glitchr.dev');         // registrar, dates, name servers
$omnistate->network('193.0.6.139');        // or 'AS3333': who holds it
```

This package holds the contract, the models (`Company`, `Establishment`, `Manager`, `FinancialYear`,
`VatCheck`, `Domain`, `Network`...), the identifier rules - checked without asking anyone - and the
Symfony bundle. Each source is a package of its own:

| Package | Source | Access |
|---|---|---|
| `omnistate/annuaire-entreprises` | French companies: the State's Recherche d'entreprises API (INSEE Sirene + INPI's RNE) | free, no key |
| `omnistate/vies` | EU VAT numbers: the European Commission's VIES (REST) | free, no key |
| `omnistate/iana` | Domains, IP ranges, AS numbers: IANA's RDAP bootstrap | free, no key |

## Identifiers

```php
Siren::isValid('901821074');               // Luhn
Siret::isValid('90182107400019');          // Luhn, La Poste's rule too
Siren::toVatNumber('901821074');           // FR53901821074
VatNumber::normalize('gr 123456789');      // EL123456789 - each member state's shape
VatNumber::isWellFormed('FR54901821074');  // false: the key disagrees with the SIREN
```

## Symfony

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

License: LGPL-3.0-or-later.
