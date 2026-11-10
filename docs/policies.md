---
title: Usage policies
order: 75
---

# Usage policies

Every register says, as data, what its service allows: how many calls (per second, day, month;
per application, key or address), what may be done with the answers (commercial use, caching,
storage, redistribution), the credit to show and the licence, the key or User-Agent it needs, and
where all this was read. That is its `policy.json`, in the schema of
[omnisense/contracts](https://github.com/glitchr-omnisense/contracts/blob/1.x/docs/policies.md),
named in its `composer.json` (`extra.omnisense.policy`) and given by its class
(`DeclaresUsagePolicyInterface::usagePolicy()`).

`Omnistate\Omnistate` puts every call to a register behind that policy's guard:

- a call over a limit is never made: `UsageLimitReachedException`, an `UnavailableException`
  with `$retryAfter` (when the window ends), `$provider` and `$limit`;
- a register's 429 (`UnavailableException::isTooManyCalls()`) holds every call to that register
  for its `$retryAfter` seconds: the next ones are a `UsageLimitReachedException` without a request;
- an answer kept in the cache is not a call, and it is kept no longer than the policy allows
  (`rights.caching`: `forbidden` keeps nothing);
- a register that declares nothing has an unknown policy: nothing limited, a 429 still held.

```php
$omnistate = new Omnistate(
    companies: [new AnnuaireEntreprises($http)],
    // The site's own limits, by register name: added to the declared ones, never looser.
    policies: ['annuaire-entreprises' => ['rateLimits' => [['max' => 5000, 'window' => 'day', 'operations' => []]]]],
    // What the site is: a use the terms forbid fails at once (UsageNotPermitted).
    usage: ['commercial' => true],
    // Where calls are counted: in memory by default; a shared one across processes
    // (omnibridge/symfony's RateLimiter over symfony/rate-limiter).
    limiter: $limiter,
);

$omnistate->policy('annuaire-entreprises')->rights->attribution->text;
$omnistate->guard('annuaire-entreprises')->remaining('company');
foreach ($omnistate->policies() as $name => $policy) { /* a "sources" page */ }
```

An operation is the contract method's name: `company`, `search`, `check`, `professional`,
`facility`, `find`, `domain`, `network`. A limit with no operations counts
them all.

## Tightening

`policies` follows `UsagePolicy::tighten()`: a site's limits are added (each counts; the
strictest wins), and one that counts the same calls as a declared one with a higher maximum is
refused; permissions only go stricter (`allowed` < `unknown` < `contract` < `forbidden`); a time
to live only shorter; nothing required is lifted and the credit, the licence and the User-Agent
pattern cannot be rewritten. Anything looser is an `InvalidPolicyException` when the register's
guard is first built.

## The registers' policies

| Register | Limits declared | Rights | Read |
|---|---|---|---|
| annuaire-entreprises | 7 a second | Licence Ouverte 2.0, "Source : Annuaire des Entreprises (INSEE, INPI)" | docs, 2026-10-25 |
| annuaire-sante | 17 a second per key | unknown | docs, 2026-10-25 |
| iana | unknown | unknown | docs, 2026-10-25 |
| matchid | unknown | unknown | docs, 2026-10-25 |
| nara | 10,000 a month per key | unknown | docs, 2026-10-25 |
| national-archives-uk | 1 a second, 3,000 a day | unknown | docs, 2026-10-25 |
| openarchieven | 4 a second | unknown | docs, 2026-10-25 |
| vies | unknown (VIES refuses by concurrency) | unknown | docs, 2026-10-25 |

The registers written before the policies carry the figures their manuals gave on 2026-10-25,
with `verifiedAt: null` and their rights `unknown` until their terms are read again at the source:
nothing is claimed that was not read.
