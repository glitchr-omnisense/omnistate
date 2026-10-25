---
title: Testing
order: 60
---

# Testing

Omnistate ships no fake register: the contracts are small enough for a test to implement one, and
every register takes its HTTP client, so a `MockHttpClient` (from `symfony/http-client`, which
every register already requires) puts recorded answers in place of the service. Neither way
reaches the network.

## An application's code: a register of its own

The facade takes any implementation of the contracts. An anonymous class answers what the test
needs:

```php
use Omnistate\Exception\UnavailableException;
use Omnistate\Model\Company;
use Omnistate\Model\CompanyStatus;
use Omnistate\Omnistate;
use Omnistate\Registry\CompanyRegistryInterface;
use Omnistate\Vies\Vies;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class SupplierCheckTest extends TestCase
{
    /** An in-memory register: the application's code is tested without HTTP at all. */
    public function testAClosedCompanyIsRefused(): void
    {
        $registry = new class implements CompanyRegistryInterface {
            public function name(): string { return 'fake'; }
            public function supports(string $identifier): bool { return true; }
            public function company(string $identifier): ?Company
            {
                return new Company($identifier, 'ACME', CompanyStatus::CLOSED, 'FR', 'fake');
            }
            public function search(string $query, int $limit = 10): array { return []; }
        };
        $omnistate = new Omnistate(companies: [$registry]);

        self::assertFalse($omnistate->company('901821074')->isActive());
    }

    /** A real register on a MockHttpClient: its mapping and its errors, offline. */
    public function testVatUnavailableIsNotInvalid(): void
    {
        $omnistate = new Omnistate(vat: [new Vies(new MockHttpClient(
            new MockResponse('{"actionSucceed":false,"errorWrappers":[{"error":"MS_UNAVAILABLE"}]}'),
        ))]);

        $this->expectException(UnavailableException::class);
        $omnistate->vat('DE123456789');
    }

    public function testAValidNumber(): void
    {
        $omnistate = new Omnistate(vat: [new Vies(new MockHttpClient(
            new MockResponse('{"valid":true,"requestDate":"2026-10-25T10:00:00Z","name":"ACME GMBH","address":"---","requestIdentifier":""}'),
        ))]);

        $check = $omnistate->vat('DE123456789');
        self::assertTrue($check->valid);
        self::assertSame('ACME GMBH', $check->name);
        self::assertNull($check->address, '"---" is nothing');
    }
}
```

```
$ vendor/bin/phpunit SupplierCheckTest.php
...                                                                 3 / 3 (100%)

Time: 00:00.026, Memory: 8.00 MB

OK (3 tests, 5 assertions)
```

(run on 2026-10-25 in the [harness](harness.md), PHPUnit 11.5, PHP 8.4)

Things worth testing in an application, because the facade does them for it:

- `UnavailableException` is not "not found": the screen that shows a company must not say
  "unknown" when the register did not answer.
- A VAT number with no member state's shape is a `VatCheck` with `valid` false and `source`
  `format`, without any register asked: a test needs no mock for it.
- With a cache, a second question is not asked again: give the facade a
  `Symfony\Component\Cache\Adapter\ArrayAdapter` and count the requests the mock receives.

Each register's own tests show its recorded answers: `Tests/Fixtures/*.json` in every
`omnistate/*` package, which ship with the package and can be served by a `MockHttpClient`
([an example](professionals.md#an-example-on-recorded-answers)).

## In Symfony

In a functional test, replace the `http_client` service the registers are built on by a
`MockHttpClient` (`framework.http_client.mock_response_factory` in the test environment), or
replace `Omnistate\Omnistate` itself (`static::getContainer()->set(Omnistate::class, $fake)`, the
service being public). The bundle's own tests build a container by hand with a synthetic
`http_client` (`Tests/Bridge/OmnistateBundleTest.php`).

## The conformance suite

`Omnistate\Testing\RegistryContractTests` is the family's contract as a PHPUnit trait, offline.
Every register's `Tests/<Service>ContractTest.php` uses it, and a register an application writes
can too. It needs `phpunit/phpunit` and `symfony/http-client` (in the register's `require-dev`).

```php
use Omnistate\MatchId\MatchId;
use Omnistate\Model\CivilQuery;
use Omnistate\Testing\RegistryContractTests;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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

### What a test case gives

| Method | Required | Default | |
|---|---|---|---|
| `registry(HttpClientInterface $http): object` | yes | - | the register under test, built on the suite's client (with a fake key where it needs one) |
| `recordedAnswers(): array` | yes | - | the service's answers, keyed `"METHOD https://host/path"`, `"METHOD /path"` (the query left out) or `"*"` for any other; each a JSON body as an array (HTTP 200), a `MockResponse`, or a callable `(string $method, string $url, array $options): MockResponse` |
| `infrastructureAnswers(): array` | no | `[]` | the answers a lookup needs before the record itself (IANA's bootstrap files), kept when the suite answers HTTP 404 to the rest |
| `companyIdentifier(): string` | no | `901821074` | a company the recorded answers know |
| `vatNumber(): string` | no | `FR53901821074` | a VAT number they know |
| `searchQuery(): string` | no | `glitch` | what `search()` and `professionals()` are asked; the answers find something |
| `professionalIdentifier(): string` | no | `10003461033` | an RPPS number they know |
| `facilityIdentifier(): string` | no | `750000010` | a FINESS number they know |
| `civilQuery(): ?CivilQuery` | for a civil register | `null` | a question it takes, the answers finding records |
| `civilIdentifier(): ?string` | for a civil register | `null` | a record they know, by its `CivilRecord::$identifier` |
| `domainName(): string` | no | `glitchr.dev` | a domain they know |
| `networkAddress(): string` | no | `AS3333` | an IP address or AS number they know |

The suite's client answers each request from `recordedAnswers()` by its method and full address,
then its method and path, then `*`; a request with none is answered HTTP 404, and when a test then
fails on an `UnavailableException` the message names every request that had no recorded answer.

### The five tests

`testItImplementsTheFamilysContractsUnderItsName` - the register implements one of the six
contracts at least, and `name()` is kebab case (`^[a-z0-9]+(-[a-z0-9]+)*$`). A civil register
declares at least one country, each ISO alpha-2 upper case, at least one kind, as a list, a
period whose start is not after its end, and a boolean `hasImages()`.

`testWhatItDoesNotTakeIsNotSupportedBeforeAnyRequest` - on a client that records every request:
a company or professional register does not support `not an identifier` and the facade's
`company()` / `professional()` throw `NotSupportedException`; a VAT register does not support
`US123456789`; a facility register does not support `not an identifier` and `facility()` throws;
a civil register does not support a question about `AQ` (Antarctica) nor one with nothing to
search on, and `civilRecords()` naming it throws; an Internet registry answers `null` for
`domain('not a domain')`. No request may have been made.

`testUnavailableIsNeverReadAsNotFound` - every question of every contract the register
implements (through the facade, but `search()` asked of the register itself) is asked seven
times, each with all requests failing one way: HTTP 401, 403, 429 with `Retry-After: 7`, 500, 503
with an HTML page, 200 with an HTML page, and a connection error. Each must be an
`UnavailableException` - not an answer, not another exception - and on the 429 its `retryAfter`
must be set and positive.

`testNotFoundIsNull` - with only `infrastructureAnswers()` kept and HTTP 404 for the rest, every
lookup (`company()`, `professional()`, `facility()`, `civilRecord()`, `domain()`, `network()`) is
`null`, never an exception.

`testTheRecordedAnswersGiveModelsOfItsOwn` - on the recorded answers, through the facade:
`company()` finds `companyIdentifier()` and `search(searchQuery(), 5)` finds at most five
companies, each with the register's name as `source`, an identifier, a name, an ISO alpha-2
country; `vat()` answers a check with that `source` and a normalized number; `professional()`
finds `professionalIdentifier()` and `professionals()` gives professionals with a `source`, an
identifier and a family name; `facility()` finds `facilityIdentifier()` with a `source`, an
identifier and a name; a civil register supports `civilQuery()`, `civilRecords()` finds records of
kinds it declares, with its `source`, an identifier and an `http(s)` URL when there is one, and
`civilRecord(civilIdentifier())` gives back a record carrying that identifier; an Internet
registry finds `domainName()` (lower case) and `networkAddress()` with a handle.

Writing a register and running the suite: [Writing a register](providers.md).
