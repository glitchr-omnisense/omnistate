---
title: Domains and networks
order: 33
---

# Domains and networks

`domain()` and `network()` ask the Internet registry - `omnistate/iana`'s `Rdap`, which finds
through IANA's RDAP bootstrap the registry that answers for a domain's top-level domain, an IP
address's range or an AS number, then asks that registry's RDAP server (WHOIS's successor, in
JSON).

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnistate\Iana\Bootstrap;
use Omnistate\Iana\Rdap;
use Omnistate\Omnistate;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
$omnistate = new Omnistate(internet: new Rdap($http, new Bootstrap($http)));

$domain = $omnistate->domain('Example.COM.');
printf("%s at %s\n  registrar %s (IANA ID %s)\n  registered %s, expires %s, changed %s\n  statuses: %s\n  name servers: %s, DNSSEC %s\n  registrant: %s\n",
    $domain->name, $domain->server, $domain->registrar, $domain->registrar?->ianaId,
    $domain->registeredAt?->format('Y-m-d'), $domain->expiresAt?->format('Y-m-d'), $domain->changedAt?->format('Y-m-d'),
    implode(', ', $domain->statuses), implode(', ', $domain->nameservers), $domain->dnssec ? 'yes' : 'no',
    $domain->registrant ?? 'not published');

$network = $omnistate->network('193.0.6.139');
printf("\n193.0.6.139: %s %s, %s to %s, %s, %s\n  abuse %s\n", $network->handle, $network->name, $network->startAddress, $network->endAddress, $network->type, $network->country, $network->abuse?->email);

$as = $omnistate->network('AS3333');
printf("\nAS3333: %s %s, %d to %d, at %s\n", $as->handle, $as->name, $as->startAutnum, $as->endAutnum, $as->server);

var_dump($omnistate->domain('not a domain'), $omnistate->domain('nobody-registered-this-0193.com'));
```

```
example.com at https://rdap.verisign.com/com/v1/
  registrar RESERVED-Internet Assigned Numbers Authority (IANA ID 376)
  registered 1995-08-14, expires 2027-08-13, changed 2026-08-14
  statuses: client delete prohibited, client transfer prohibited, client update prohibited
  name servers: elliott.ns.cloudflare.com, hera.ns.cloudflare.com, DNSSEC yes
  registrant: not published

193.0.6.139: 193.0.0.0 - 193.0.7.255 RIPE-NCC, 193.0.0.0 to 193.0.7.255, ASSIGNED PA, NL
  abuse abuse@ripe.net

AS3333: AS3333 RIPE-NCC-AS, 3333 to 3333, at https://rdap.db.ripe.net/
NULL
NULL
```

(run on 2026-10-25 in the [harness](harness.md): IANA's bootstrap files, then Verisign's and the
RIPE NCC's RDAP servers)

## A domain

The name is trimmed, lower-cased and loses its trailing dot (`Example.COM.` is `example.com`); with
`ext-intl`, an international name is asked in its ASCII form. Something that is not a domain
name is `null` without a request. The RDAP server is the one IANA's `dns` bootstrap lists for the
longest suffix of the name; a top-level domain with no RDAP server is `null` too, and so is a name
the registry does not know (HTTP 404).

What comes back ([Domain](concepts.md#domain-and-network)): the registrar (its name, IANA ID,
page), the `registration`, `expiration` and `last changed` dates, the RDAP statuses, the name
servers, whether the delegation is DNSSEC-signed, and the registrant only when the registry still
publishes one: a contact with nothing but a handle (redacted, since the GDPR) is `null`.

## An IP range, an autonomous system

`network()` takes an IPv4 or IPv6 address (the most specific range of IANA's `ipv4` or `ipv6`
bootstrap names the regional registry), or an AS number with or without `AS` (`AS3333`, `3333`;
the `asn` bootstrap). The answer is the range - its handle, name, type, country, first and last
address - or the autonomous system - its numbers -, its holder (`registrant`) and its abuse
contact, looked for among nested entities too.

## The bootstrap files

`Iana\Bootstrap` reads IANA's four bootstrap files (`https://data.iana.org/rdap/dns.json`,
`ipv4.json`, `ipv6.json`, `asn.json`) the first time each is needed, and keeps them in memory for
the object's life. Given a cache, it keeps them there a day (`86400` s, keys
`omnistate.iana.bootstrap.<file>`), as IANA asks clients not to fetch them on every lookup:

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$cache = new FilesystemAdapter();
$rdap = new Rdap($http, new Bootstrap($http, $cache));
$omnistate = new Omnistate(internet: $rdap, cache: $cache);   // the answers too
```

The Symfony bundle gives `Bootstrap` the same cache pool as the facade.

## Errors

- IANA's bootstrap not answering, an HTTP error on it, an answer that is not JSON:
  `UnavailableException` (`retryAfter` from a 429's `Retry-After`, else 60 s).
- An RDAP server answering HTTP 429 or 5xx, or not answering: `UnavailableException`.
- No Internet registry installed: `NotSupportedException` (`No Internet registry installed: add
  omnistate/iana.`).

> [!WARNING]
> An RDAP server's HTTP 401, 403 or another 4xx than 404 is read as `null`, "not registered", and
> a 429 without `Retry-After` from an RDAP server has no `retryAfter`: both contradict the family's
> rule ([Errors](errors.md#the-familys-rule)); the conformance suite does not see it because its
> failures hit the bootstrap first. Listed in [Verified, and not](verified.md).
