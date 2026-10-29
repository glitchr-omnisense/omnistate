---
title: VAT numbers
order: 32
---

# VAT numbers

`vat()` says whether an intra-EU VAT number exists and whose it is. Its shape is checked first,
without asking anyone; then the VAT register installed for its country is asked -
`omnistate/vies`, the European Commission's VIES, for the 27 member states and Northern Ireland.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnistate\Exception\NotSupportedException;
use Omnistate\Exception\UnavailableException;
use Omnistate\Omnistate;
use Omnistate\Vies\Vies;
use Symfony\Component\HttpClient\HttpClient;

$omnistate = new Omnistate(vat: [new Vies(HttpClient::create())]);

foreach (['IE 6388047V', 'FR54901821074', 'gr 12345', 'NO123456789MVA', 'FR39356000000'] as $number) {
    try {
        $check = $omnistate->vat($number);
        printf("%-16s %-9s source %-6s %s\n", $check->number, $check->valid ? 'valid' : 'not valid', $check->source, $check->name ?? '');
    } catch (UnavailableException $e) {
        printf("%-16s VIES did not answer: %s (retry after %s s)\n", $number, $e->getMessage(), $e->retryAfter ?? '-');
    } catch (NotSupportedException $e) {
        printf("%-16s %s\n", $number, $e->getMessage());
    }
}
```

```
IE6388047V       valid     source vies   GOOGLE IRELAND LIMITED
FR54901821074    not valid source format 
GR12345          not valid source format 
NO123456789MVA   not valid source format 
FR39356000000    valid     source vies   SA LA POSTE-DIRECTION GENERALE DE LA PO
```

(run on 2026-10-25 in the [harness](harness.md), against VIES)

## The shape first

The facade normalizes the number with `Identifier\VatNumber::normalize()` - spaces, dots and
dashes go, upper case, `GR` becomes `EL` (VIES's prefix for Greece), and a SIREN or a SIRET
becomes its French number - and checks it with `VatNumber::isWellFormed()`: the member state's
shape after the prefix, and for France the key agreeing with the SIREN. A number that fails is
answered at once, with no request: a `VatCheck` whose `valid` is `false` and whose `source` is
`format`, its `number` the input without its spaces, upper case. That covers a key that does not
match (`FR54901821074`), a number too short (`GR12345`) and a country outside the EU
(`NO123456789MVA`, a Norwegian number).

A well-formed number goes to the first VAT register whose `supports()` takes its country. When
none does - no VAT register installed - it is a `NotSupportedException` (`No VAT registry
installed answers for DE.`). With `omnistate/vies` installed, every well-formed number has a
register: the shapes and VIES's countries are the same list (`VatNumber::FORMATS`).

## The consultation number

Given your own VAT number as the requester, VIES answers a consultation number - the proof that
the check was made that day, to keep with a reverse-charge invoice:

```php
$omnistate = new Omnistate(vat: [new Vies($http)], requester: 'FR53901821074');   // every check

$check = $omnistate->vat('IE6388047V');                         // or vat($number, requester: '...') for one
$check->consultationNumber;   // null when VIES gave none
$check->checkedAt;            // VIES's date of the check
```

The requester is normalized like the number. A requester that has no member state's shape becomes
`null` and is not sent: the check is made, without a consultation number.

A check VIES refuses - `INVALID_INPUT`, `INVALID_REQUESTER_INFO` for a requester it does not
accept, `VAT_BLOCKED` - is an `OmnistateException` naming the code, never `valid: false`: asking
again gives the same answer, so fix the requester (or check without one).

## Unavailable is not invalid

VIES only relays: each member state's service has its hours, and VIES limits concurrent calls.
`omnistate/vies` reads these answers as `UnavailableException`, never as a number that does not
exist: `MS_UNAVAILABLE` (`retryAfter` 600 s), `TIMEOUT`, `SERVICE_UNAVAILABLE`,
`MS_MAX_CONCURRENT_REQ`, `GLOBAL_MAX_CONCURRENT_REQ` and their `_TIME` variants, `SERVER_BUSY`,
`IP_BLOCKED`, any error code but the three refusals above and VIES's verdicts `VALID` and
`INVALID`, an HTTP 429 (its `Retry-After`, else 60 s), an answer that is not JSON, and no answer
at all.

```php
try {
    $check = $omnistate->vat($number);
} catch (UnavailableException $e) {
    // keep the order pending, ask again in $e->retryAfter seconds (null: no estimate)
}
```

On 2026-10-25 France's service answered `MS_MAX_CONCURRENT_REQ` for `FR53901821074` while
`FR39356000000` was answered a few minutes later: a busy answer is passing.

In Symfony, `#[VatNumber(checkExistence: true)]` does the same check in a form, and lets the
value pass when VIES does not answer ([Constraints](symfony.md#constraints)).

## What is kept

With a cache, a check is kept `ttl` seconds under the register's name, the normalized number and
the requester: the same number with another requester is asked again. A kept `VatCheck` carries
the `checkedAt` and `consultationNumber` of the first check - store the check itself when it is a
proof, not only the cache.
