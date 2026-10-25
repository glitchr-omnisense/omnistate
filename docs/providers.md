# Writing a register

A register is a package of its own (`omnistate/<service>`, namespace `Omnistate\<Service>\`) that
requires `omniscient/omnistate` and `symfony/http-client`: one class, built on an
`HttpClientInterface` (and a key where the service wants one), implementing one or more of the
family's contracts:

| Contract | Asks | Facade |
|---|---|---|
| `Registry\CompanyRegistryInterface` | `supports($identifier)`, `company($identifier)`, `search($query, $limit)` | `company()`, `search()` |
| `Registry\VatRegistryInterface` | `supports($number)`, `check($number, $requester)` | `vat()` |
| `Registry\ProfessionalRegistryInterface` | `supports($identifier)`, `professional($identifier)`, `search($name, $postcode, $limit)` | `professional()`, `professionals()` |
| `Registry\FacilityRegistryInterface` | `supportsFacility($identifier)`, `facility($identifier)` | `facility()` |
| `Registry\CivilRegistryInterface` | `countries()`, `kinds()`, `period()`, `hasImages()`, `supports($query)`, `search($query)`, `find($identifier)` | `civilRecords()`, `civilRecord()` |
| `Registry\InternetRegistryInterface` | `domain($name)`, `network($address)` | `domain()`, `network()` |

`name()` is a short name (`annuaire-entreprises`), the `$source` of every model it returns.
Identifier rules (SIREN, SIRET, VAT numbers, RPPS, FINESS) live in `Omnistate\Identifier\`: a
register checks the form there and asks nothing about an identifier that is not one.

## Errors: the family's rule

Not knowing is an answer; not answering is not. A register never reads one as the other:

| What happens | What the register does |
|---|---|
| it does not know the company, the professional, the record, the domain (HTTP 404, 410, an empty result) | `null` from a lookup, `[]` from a search |
| the identifier or the question is not one for it | `supports()` says no; the facade throws `NotSupportedException` without asking |
| HTTP 401 or 403 (a key missing or refused, a firewall) | `UnavailableException` |
| HTTP 429 | `UnavailableException` with `$retryAfter` in seconds: the answer's `Retry-After` when it gives one, else the register's own estimate (NARA: until its monthly quota renews) |
| HTTP 5xx, no answer (DNS, connection, timeout), an answer that cannot be read (an HTML page in place of JSON) | `UnavailableException` |
| a question the service refuses as malformed (HTTP 400) | `null`/`[]` or an `OmnistateException` with the service's reason, as the register documents |

A VAT check that cannot be made is an `UnavailableException`, never a `VatCheck` saying "not valid".

## Conformance suite

`Omnistate\Testing\RegistryContractTests` is the family's contract as PHPUnit tests, offline. Every
register's `Tests/<Service>ContractTest.php` uses it:

```php
use Omnistate\Testing\RegistryContractTests;

final class MatchIdContractTest extends TestCase
{
    use RegistryContractTests;

    protected function registry(HttpClientInterface $http): object
    {
        return new MatchId($http);
    }

    protected function recordedAnswers(): array
    {
        return ['*' => static fn (string $method, string $url) => new MockResponse(
            file_get_contents(__DIR__.'/Fixtures/'.(str_contains($url, '/id/') ? 'id.json' : 'search.json')),
        )];
    }

    protected function civilQuery(): ?CivilQuery { return new CivilQuery(familyName: 'Chirac', givenName: 'Jacques'); }
    protected function civilIdentifier(): ?string { return 'fohpDS17K0Ez'; }
}
```

The register is built on the suite's `MockHttpClient`; `recordedAnswers()` gives the service's
answers by `"METHOD https://host/path"`, `"METHOD /path"` or `"*"` (a JSON body as an array, a
`MockResponse`, or a callable). A request with no recorded answer is answered HTTP 404, and the
failure names it. Hooks name what the recorded answers know: `companyIdentifier()`,
`vatNumber()`, `searchQuery()`, `professionalIdentifier()`, `facilityIdentifier()`,
`civilQuery()`, `civilIdentifier()`, `domainName()`, `networkAddress()`; `infrastructureAnswers()`
keeps what a lookup needs before the record itself (IANA's bootstrap files) when the suite answers
HTTP 404 to the rest.

What it checks, for each contract the register implements, asked through the `Omnistate` facade:

- it implements one of the contracts at least, under a short name; a civil register's countries
  are ISO 3166-1 alpha-2, its kinds and period well formed;
- an identifier of another kind, a civil question about another country or with nothing to search
  on, is not supported: `NotSupportedException` from the facade, and no HTTP request;
- HTTP 401, 403, 429, 500, 503 (an HTML page), HTTP 200 with an HTML page, and no answer at all are
  an `UnavailableException` for every question - never `null`, `[]` or a `VatCheck`; a 429's carries
  `$retryAfter`;
- HTTP 404 on every lookup is `null`;
- on the recorded answers, every lookup and search gives models carrying the register's name as
  `$source`, their identifier and name; a civil record is of a kind the register holds, and
  `find()` gives back the record a search named.

Run it with the register's other tests: in the workspace (`~/Sites/omnistate`, path repositories
and one `phpunit.xml.dist`), or through the Docker harness (`docs/harness.md`).
