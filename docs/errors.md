---
title: Errors
order: 50
---

## Errors

Three exceptions, in `Omnistate\Exception`:

| Exception | Parent | Means |
|---|---|---|
| `OmnistateException` | `\RuntimeException` | the family's base class; thrown as such when a register refused the question itself |
| `NotSupportedException` | `OmnistateException` | no register installed takes that question: add its package |
| `UnavailableException` | `OmnistateException` | the register did not answer; `public readonly ?int $retryAfter`, in seconds, when there is an estimate |

`UnavailableException::__construct(string $message, ?int $retryAfter = null, ?\Throwable
$previous = null)`: the HTTP client's exception, when there was one, is the previous exception.

### The family's rule

Not knowing is an answer; not answering is not. No register reads one as the other:

| What happens | What the application gets |
|---|---|
| the register does not know the company, the professional, the record, the domain | `null` from a lookup, `[]` from a search |
| a VAT number that does not exist | a `VatCheck` with `valid` false |
| the identifier or the question is not one for any register installed | `NotSupportedException`, from the facade, before any request |
| HTTP 401 or 403 (a key missing or refused) | `UnavailableException` |
| HTTP 429 | `UnavailableException` with `retryAfter` |
| HTTP 5xx, no answer (DNS, connection, timeout), an answer that cannot be read (an HTML page in place of JSON) | `UnavailableException` |
| a question the service refuses as malformed | as each register documents: `[]`, `null`, or an `OmnistateException` with the service's reason |

So a screen can say "unknown" only on `null`, `[]` or `valid: false`, and "the register did not
answer, try again" on `UnavailableException`. A VAT check that cannot be made is never a
`VatCheck` saying "not valid". The conformance suite checks the rule in every register
([Testing](testing.md#the-conformance-suite)).

### When each is thrown

`NotSupportedException`, by the facade only:

| Message | Thrown by |
|---|---|
| `No company registry installed knows "<identifier>".` | `company()` |
| `No VAT registry installed answers for <country>.` | `vat()`, for a well-formed number |
| `No professional registry installed knows "<identifier>".` | `professional()` |
| `No facility registry installed knows "<identifier>".` | `facility()` |
| `No civil registry installed takes that question.` | `civilRecords()` without a register named |
| `The civil registry "<name>" is not installed, or does not take that question.` | `civilRecords()` with a register named |
| `The civil registry "<name>" is not installed.` | `civilRecord()` |
| `No Internet registry installed: add omnistate/iana.` | `domain()`, `network()` |

`UnavailableException`, by the registers, and its `retryAfter`:

| Register | HTTP 429 | Other cases |
|---|---|---|
| `annuaire-entreprises` | `Retry-After`, else 1 s | 5xx; 401, 403 and every 4xx but 400 and 404; no answer; not JSON |
| `vies` | `Retry-After` (at least 1), else 60 s | VIES's `MS_UNAVAILABLE` (600 s), `TIMEOUT`, `SERVICE_UNAVAILABLE`, `MS_MAX_CONCURRENT_REQ`, `GLOBAL_MAX_CONCURRENT_REQ`, `SERVER_BUSY`, `IP_BLOCKED`; an answer with neither `valid` nor an error; no answer; not JSON |
| `iana` (`Bootstrap`) | `Retry-After` (at least 1), else 60 s | any HTTP error on a bootstrap file, no answer, not JSON |
| `iana` (`Rdap`) | `Retry-After`, else none | 5xx, no answer, not JSON |
| `annuaire-sante` | `Retry-After`, else 1 s | no key (before any request), 401, 403, 5xx, another 4xx but 404, no answer, not JSON |
| `matchid` | `Retry-After`, else 60 s | 401, 403, 5xx, no answer, not JSON |
| `openarchieven` | `Retry-After` (at least 1), else 1 s | 401, 403, 5xx, no answer, not JSON |
| `national-archives-uk` | `Retry-After` (at least 1), else 60 s | 401, 403, 5xx, no answer, not JSON |
| `nara` | the seconds until the first of the next month (UTC), at least 60 | 401, 403, 5xx, the Catalog's web page in place of JSON (no key or a wrong one), no answer |

`OmnistateException` itself, a refusal of the question:

| Register | When |
|---|---|
| `matchid` | an HTTP status of 400 or more not listed above (a date it cannot read: 400), with matchID's message |
| `openarchieven` | an `error_code` in the body (HTTP 200) on a search; on `find()` it is `null` |
| `national-archives-uk` | a 4xx other than 401, 403, 429, and 204/404 on `find()` |
| `nara` | a 4xx other than 401, 403, 429, and 404 on `find()` |

Other exceptions, from code an application calls directly:

- `\InvalidArgumentException`: `Siren::toVatNumber()`, `Siret::siren()`, `Rpps::toIdnps()` and
  `Rpps::withKey()` given something that is not one; `AnnuaireSante::professional()` and
  `::facility()` called directly with something that is not an RPPS or a FINESS number (the facade
  checks `supports()` first and throws `NotSupportedException` instead).
- `Symfony\Component\Validator\Exception\UnexpectedTypeException`: a constraint validator given
  another constraint (a wiring error).

### What to catch

```php
use Omnistate\Exception\NotSupportedException;
use Omnistate\Exception\UnavailableException;

try {
    $company = $omnistate->company($typed);
} catch (NotSupportedException) {
    // not an identifier any register installed takes: say so in the form
} catch (UnavailableException $e) {
    // the register did not answer: keep what was typed, ask again in $e->retryAfter seconds
}
if (null === $company) {
    // no such company
}
```

Catching `OmnistateException` catches all three. A cache never keeps an exception: the next call
asks again.

Two registers depart from the rule in cases the conformance suite does not reach; they are listed
in [Verified, and not](verified.md#defects-found-in-the-code): `Rdap` reads an RDAP server's 401,
403 or other 4xx as `null`, and `AnnuaireSante` reads a `Retry-After` written as a date as 0 s.
