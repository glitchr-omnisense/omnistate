# Symfony

Omnistate runs without a framework ([installation](installation.md)); in a Symfony application its
bundle does the wiring. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel`, and `symfony/validator`, `symfony/form`, `symfony/routing`,
`symfony/http-foundation` for the constraints and the company search - are not required by
`glitchr/omnistate`: the application has them, and nothing of them is loaded outside Symfony.

Register `Omnistate\Bridge\Symfony\OmnistateBundle` (no Flex recipe):

```php
// config/bundles.php
return [
    // ...
    Omnistate\Bridge\Symfony\OmnistateBundle::class => ['all' => true],
];
```

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

Every `omnistate/*` package installed is registered on the application's `http_client`, and
`Omnistate\Omnistate` is autowired, its answers kept in the cache pool. An application's own
registry - a class implementing `CompanyRegistryInterface`, `VatRegistryInterface`,
`ProfessionalRegistryInterface` or `CivilRegistryInterface` - is registered too, autoconfigured.

```php
public function __construct(private readonly Omnistate $omnistate)
{
}
```

A registry that does not answer throws `UnavailableException` (with `$retryAfter` when it said):
that is not an answer about the company or the number.

## Constraints

```php
use Omnistate\Bridge\Symfony\Validator as Assert;

#[Assert\Siren]
public ?string $siren = null;

#[Assert\Siret]
public ?string $siret = null;

#[Assert\VatNumber(checkExistence: true)]   // its shape, then VIES
public ?string $vatNumber = null;
```

## Company search

`CompanySearchType` is a search field that suggests companies as one types and, on a pick, fills
the sibling fields named in `fill` (`['vatNumber' => 'vatNumber']`: the French VAT number derived
from the SIREN). It asks `CompanySearchController`, `GET /omnistate/company/search?q=`: route it
by importing `Bridge/Symfony/Controller/` (type `attribute`), or through a subclass in a
directory already imported.

**Protect that route.** The controller checks nothing itself: left open, anyone can use your
server as a relay to the registries and spend their rate limits (Recherche d'entreprises: about
7 calls a second). Put it behind your back office's access control, or give your subclass an
`#[IsGranted]`.
