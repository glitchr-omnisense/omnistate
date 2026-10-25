---
title: Laravel
order: 16
---

# Laravel

omnibridge/laravel, the bridge that hosts omnibase in Laravel, has no Omnistate provider, and
there is no other Laravel package for Omnistate. None is needed: the facade is plain PHP, and a
service provider of the application builds it once, from a config file, with a
`symfony/http-client` client (every register requires that package; Laravel's own HTTP client is
Guzzle's, which the registers do not take).

```php
// config/omnistate.php
return [
    'ttl' => 86400,
    'timeout' => 10,
    'requester' => env('OMNISTATE_REQUESTER'),       // your VAT number: VIES answers a consultation number
    'annuaire_sante_key' => env('ESANTE_API_KEY'),   // omnistate/annuaire-sante
    'matchid_token' => env('MATCHID_TOKEN'),         // omnistate/matchid, optional
    'nara_key' => env('NARA_API_KEY'),               // omnistate/nara
];
```

```php
// app/Providers/OmnistateServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Omnistate\AnnuaireEntreprises\AnnuaireEntreprises;
use Omnistate\AnnuaireSante\AnnuaireSante;
use Omnistate\Iana\Bootstrap;
use Omnistate\Iana\Rdap;
use Omnistate\MatchId\MatchId;
use Omnistate\Omnistate;
use Omnistate\OpenArchieven\OpenArchieven;
use Omnistate\Vies\Vies;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\HttpClient\HttpClient;

final class OmnistateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Omnistate::class, function ($app): Omnistate {
            $http = HttpClient::create();
            $timeout = (float) config('omnistate.timeout');
            // Laravel's cache store is a PSR-16 cache: symfony/cache's Psr16Adapter makes it the
            // Symfony\Contracts\Cache\CacheInterface the facade and IANA's bootstrap take.
            $cache = new Psr16Adapter($app['cache.store'], 'omnistate');

            return new Omnistate(
                companies: [new AnnuaireEntreprises($http, $timeout)],
                vat: [new Vies($http, $timeout)],
                internet: new Rdap($http, new Bootstrap($http, $cache, $timeout), $timeout),
                cache: $cache,
                ttl: (int) config('omnistate.ttl'),
                requester: config('omnistate.requester'),
                professionals: [new AnnuaireSante($http, config('omnistate.annuaire_sante_key'), $timeout)],
                civil: [
                    new MatchId($http, config('omnistate.matchid_token'), $timeout),
                    new OpenArchieven($http, $timeout),
                ],
            );
        });
    }
}
```

Register the provider (`bootstrap/providers.php` on Laravel 11 and later), then inject the facade
where it is needed:

```php
use Omnistate\Omnistate;

final class SupplierController
{
    public function show(Omnistate $omnistate, string $siren)
    {
        $company = $omnistate->company($siren);
        // ...
    }
}
```

List in the provider every register the application uses: unlike the Symfony bundle, a
hand-built facade knows only the registers it is given. `symfony/cache` is an extra requirement
of this example (`composer require symfony/cache`); without a cache, leave `cache` out and every
call asks the register. Configuration caching (`php artisan config:cache`) keeps working: the
options are plain values read through `config()`.

The Symfony bridge's pieces - the validation constraints, the company search field and its
endpoint - have no Laravel counterpart. The identifier rules they use are plain PHP
(`Omnistate\Identifier\Siren::isValid()`...) and fit a Laravel validation rule written by hand:

```php
use Omnistate\Identifier\VatNumber;

$request->validate([
    'vat_number' => ['nullable', function (string $attribute, mixed $value, \Closure $fail) {
        if (!VatNumber::isWellFormed((string) $value)) {
            $fail('This is not a valid EU VAT number.');
        }
    }],
]);
```

This page was written from the facade's and the registers' constructors; it was not run in a
Laravel application. The provider's construction was run in plain PHP in the
[harness](harness.md) on 2026-10-25, with a `symfony/cache` `ArrayAdapter` in place of the
`Psr16Adapter` (the harness has no `psr/simple-cache`, which Laravel brings): the facade built,
a company asked twice, one request made.
