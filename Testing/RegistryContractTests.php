<?php

namespace Omnistate\Testing;

use Omnistate\Exception\NotSupportedException;
use Omnistate\Exception\UnavailableException;
use Omnistate\Model\CivilQuery;
use Omnistate\Model\CivilRecord;
use Omnistate\Model\Company;
use Omnistate\Model\Professional;
use Omnistate\Omnistate;
use Omnistate\Registry\CivilRegistryInterface;
use Omnistate\Registry\CompanyRegistryInterface;
use Omnistate\Registry\FacilityRegistryInterface;
use Omnistate\Registry\InternetRegistryInterface;
use Omnistate\Registry\ProfessionalRegistryInterface;
use Omnistate\Registry\VatRegistryInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What every Omnistate register does, as PHPUnit tests, offline: a provider's
 * test case uses this trait, gives its registry (built on the HTTP client the
 * suite hands it) and the register's recorded answers. The suite checks each
 * of the family's contracts the registry implements (CompanyRegistryInterface,
 * VatRegistryInterface, ProfessionalRegistryInterface, FacilityRegistryInterface,
 * CivilRegistryInterface, InternetRegistryInterface), asked through the
 * Omnistate facade as an application asks:
 *
 *   final class MatchIdContractTest extends TestCase
 *   {
 *       use RegistryContractTests;
 *
 *       protected function registry(HttpClientInterface $http): object { return new MatchId($http); }
 *       protected function recordedAnswers(): array { return ['GET /deaths/search' => json_decode(file_get_contents(...), true)]; }
 *       protected function civilQuery(): ?CivilQuery { return new CivilQuery(familyName: 'Chirac'); }
 *       protected function civilIdentifier(): ?string { return 'fohpDS17K0Ez'; }
 *   }
 *
 * It checks:
 * - it implements at least one of the family's contracts, under a short name (its models' $source);
 * - what it does not take (an identifier of another kind, another country's question) is a
 *   NotSupportedException from the facade, before any HTTP request; a civil register's coverage
 *   (countries, kinds, period) is well formed;
 * - the family's rule on errors (docs/providers.md): when the register answers HTTP 401, 403,
 *   429, 500, 503, a body that is not what it should be, or cannot be reached, every question is
 *   an UnavailableException - never null, an empty list or an invalid VAT number, which would
 *   read "unavailable" as "not found"; a 429 carries $retryAfter;
 * - HTTP 404 on a lookup (a company, a professional, a facility, a record, a domain, a network)
 *   is null: not known;
 * - on the recorded answers: each lookup and search gives models carrying the registry's name as
 *   $source and their identifier, a civil record of a kind the register says it holds.
 *
 * No test reaches the network: every request goes to the suite's MockHttpClient, and one with no
 * recorded answer is answered HTTP 404 and named in the failure.
 *
 * Requires phpunit/phpunit and symfony/http-client (the provider's, in require-dev).
 */
trait RegistryContractTests
{
    /** @var list<string> */
    private array $contractUnanswered = [];

    /** The register under test, talking through $http (with a fake key, where it needs one). */
    abstract protected function registry(HttpClientInterface $http): object;

    /**
     * The register's answers, by "METHOD https://host/path", "METHOD /path" (the query left out)
     * or "*" for any other request: a JSON body as an array (HTTP 200), a MockResponse, or a
     * callable (string $method, string $url, array $options): MockResponse.
     *
     * @return array<string, array<mixed>|MockResponse|callable>
     */
    abstract protected function recordedAnswers(): array;

    /** A company the recorded answers know, by an identifier it supports. */
    protected function companyIdentifier(): string
    {
        return '901821074';
    }

    /** A VAT number the recorded answers know. */
    protected function vatNumber(): string
    {
        return 'FR53901821074';
    }

    /** What search() and professionals() are asked; the recorded answers find something. */
    protected function searchQuery(): string
    {
        return 'glitch';
    }

    /** A professional the recorded answers know (an RPPS number). */
    protected function professionalIdentifier(): string
    {
        return '10003461033';
    }

    /** A facility the recorded answers know (a FINESS number). */
    protected function facilityIdentifier(): string
    {
        return '750000010';
    }

    /** A question the civil register takes, the recorded answers finding records. */
    protected function civilQuery(): ?CivilQuery
    {
        return null;
    }

    /** A record the recorded answers know, by its CivilRecord::$identifier. */
    protected function civilIdentifier(): ?string
    {
        return null;
    }

    /** A domain the recorded answers know. */
    protected function domainName(): string
    {
        return 'glitchr.dev';
    }

    /** An IP address or AS number the recorded answers know. */
    protected function networkAddress(): string
    {
        return 'AS3333';
    }

    public function testItImplementsTheFamilysContractsUnderItsName(): void
    {
        $registry = $this->registry(new MockHttpClient());
        $contracts = array_filter([CompanyRegistryInterface::class, VatRegistryInterface::class, ProfessionalRegistryInterface::class, FacilityRegistryInterface::class, CivilRegistryInterface::class, InternetRegistryInterface::class], static fn (string $contract) => $registry instanceof $contract);
        self::assertNotSame([], $contracts, 'a register implements one of the family\'s contracts at least');
        self::assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $registry->name(), 'a short name, its models\' $source');

        if ($registry instanceof CivilRegistryInterface) {
            self::assertNotSame([], $registry->countries());
            foreach ($registry->countries() as $country) {
                self::assertMatchesRegularExpression('/^[A-Z]{2}$/', $country, 'countries: ISO 3166-1 alpha-2');
            }
            self::assertNotSame([], $registry->kinds());
            self::assertIsList($registry->kinds());
            $period = $registry->period();
            if (null !== $period?->from && null !== $period->to) {
                self::assertLessThanOrEqual($period->to->year, $period->from->year);
            }
            self::assertIsBool($registry->hasImages());
        }
    }

    public function testWhatItDoesNotTakeIsNotSupportedBeforeAnyRequest(): void
    {
        $requests = [];
        $registry = $this->registry(new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = $method.' '.$url;

            return new MockResponse('{}', ['http_code' => 500]);
        }));
        $omnistate = $this->omnistate($registry);
        $refuse = static function (\Closure $ask, string $what): void {
            try {
                $ask();
                self::fail($what.': asked of a register that does not take it');
            } catch (NotSupportedException $e) {
                self::assertNotSame('', $e->getMessage());
            }
        };

        if ($registry instanceof CompanyRegistryInterface) {
            self::assertFalse($registry->supports('not an identifier'));
            $refuse(static fn () => $omnistate->company('not an identifier'), 'company()');
        }
        if ($registry instanceof VatRegistryInterface) {
            self::assertFalse($registry->supports('US123456789'));
        }
        if ($registry instanceof ProfessionalRegistryInterface) {
            self::assertFalse($registry->supports('not an identifier'));
            $refuse(static fn () => $omnistate->professional('not an identifier'), 'professional()');
        }
        if ($registry instanceof FacilityRegistryInterface) {
            self::assertFalse($registry->supportsFacility('not an identifier'));
            $refuse(static fn () => $omnistate->facility('not an identifier'), 'facility()');
        }
        if ($registry instanceof CivilRegistryInterface) {
            $elsewhere = new CivilQuery(familyName: 'Smith', country: 'AQ');
            self::assertFalse($registry->supports($elsewhere), 'a country it does not hold');
            $refuse(static fn () => $omnistate->civilRecords($elsewhere, $registry->name()), 'civilRecords()');
            self::assertFalse($registry->supports(new CivilQuery()), 'a question with nothing to search on');
        }
        if ($registry instanceof InternetRegistryInterface) {
            self::assertNull($registry->domain('not a domain'), 'not a domain name: null, not asked');
        }
        self::assertSame([], $requests, 'refused before any HTTP request');
    }

    public function testUnavailableIsNeverReadAsNotFound(): void
    {
        $failures = [
            'HTTP 401' => fn () => new MockResponse('{"message":"Unauthorized"}', ['http_code' => 401]),
            'HTTP 403' => fn () => new MockResponse('{"message":"Forbidden"}', ['http_code' => 403]),
            'HTTP 429' => fn () => new MockResponse('{"message":"Too many requests"}', ['http_code' => 429, 'response_headers' => ['retry-after' => '7']]),
            'HTTP 500' => fn () => new MockResponse('{"message":"Internal error"}', ['http_code' => 500]),
            'HTTP 503 (an HTML page)' => fn () => new MockResponse('<html><body>Service Unavailable</body></html>', ['http_code' => 503, 'response_headers' => ['content-type' => 'text/html']]),
            'HTTP 200, not the expected body' => fn () => new MockResponse('<html><body>Maintenance</body></html>', ['response_headers' => ['content-type' => 'text/html']]),
            'unreachable' => fn () => new MockResponse('', ['error' => 'Could not resolve host']),
        ];
        $problems = [];
        $asked = 0;
        foreach ($failures as $failure => $response) {
            $registry = $this->registry(new MockHttpClient(static fn () => $response()));
            foreach ($this->questions($registry, $this->omnistate($registry)) as $question => $ask) {
                ++$asked;
                try {
                    $answer = $ask();
                    $problems[] = \sprintf('%s on %s: answered %s instead of an UnavailableException', $question, $failure, get_debug_type($answer));
                } catch (UnavailableException $e) {
                    if ('HTTP 429' === $failure && (null === $e->retryAfter || $e->retryAfter <= 0)) {
                        $problems[] = \sprintf('%s on HTTP 429: no $retryAfter', $question);
                    }
                } catch (\Throwable $e) {
                    $problems[] = \sprintf('%s on %s: %s ("%s") instead of an UnavailableException', $question, $failure, $e::class, $e->getMessage());
                }
            }
        }
        self::assertGreaterThan(0, $asked);
        self::assertSame([], $problems, 'a register that does not answer is unavailable, not silent');
    }

    public function testNotFoundIsNull(): void
    {
        $registry = $this->registry($this->contractHttp(notFound: true));
        $omnistate = $this->omnistate($registry);
        $problems = [];
        foreach ($this->questions($registry, $omnistate) as $question => $ask) {
            if (!str_ends_with($question, '(identifier)')) {
                continue;
            }
            try {
                if (null !== $answer = $ask()) {
                    $problems[] = \sprintf('%s on HTTP 404: %s instead of null', $question, get_debug_type($answer));
                }
            } catch (\Throwable $e) {
                $problems[] = \sprintf('%s on HTTP 404: %s ("%s") instead of null', $question, $e::class, $e->getMessage());
            }
        }
        self::assertSame([], $problems, 'what the register does not know is null');
    }

    public function testTheRecordedAnswersGiveModelsOfItsOwn(): void
    {
        $registry = $this->registry($this->contractHttp());
        $omnistate = $this->omnistate($registry);
        $name = $registry->name();

        if ($registry instanceof CompanyRegistryInterface) {
            $company = $this->answered(fn () => $omnistate->company($this->companyIdentifier()), 'company()');
            self::assertNotNull($company, 'company(): the recorded answers know companyIdentifier()');
            $this->assertCompany($company, $name);
            $found = $this->answered(fn () => $omnistate->search($this->searchQuery(), 5), 'search()');
            self::assertNotSame([], $found, 'search(): the recorded answers find searchQuery()');
            self::assertLessThanOrEqual(5, \count($found));
            foreach ($found as $one) {
                $this->assertCompany($one, $name);
            }
        }
        if ($registry instanceof VatRegistryInterface) {
            $check = $this->answered(fn () => $omnistate->vat($this->vatNumber()), 'vat()');
            self::assertSame($name, $check->source);
            self::assertMatchesRegularExpression('/^[A-Z]{2}[0-9A-Z+*]+$/', $check->number, 'vat(): the number, normalized');
        }
        if ($registry instanceof ProfessionalRegistryInterface) {
            $professional = $this->answered(fn () => $omnistate->professional($this->professionalIdentifier()), 'professional()');
            self::assertNotNull($professional, 'professional(): the recorded answers know professionalIdentifier()');
            $this->assertProfessional($professional, $name);
            foreach ($this->answered(fn () => $omnistate->professionals($this->searchQuery()), 'professionals()') as $one) {
                $this->assertProfessional($one, $name);
            }
        }
        if ($registry instanceof FacilityRegistryInterface) {
            $facility = $this->answered(fn () => $omnistate->facility($this->facilityIdentifier()), 'facility()');
            self::assertNotNull($facility, 'facility(): the recorded answers know facilityIdentifier()');
            self::assertSame($name, $facility->source);
            self::assertNotSame('', $facility->identifier);
            self::assertNotSame('', $facility->name);
        }
        if ($registry instanceof CivilRegistryInterface) {
            $query = $this->civilQuery();
            self::assertNotNull($query, 'a civil register: civilQuery() gives a question it takes');
            self::assertTrue($registry->supports($query));
            $records = $this->answered(fn () => $omnistate->civilRecords($query, $name), 'civilRecords()');
            self::assertNotSame([], $records, 'civilRecords(): the recorded answers find civilQuery()');
            foreach ($records as $record) {
                $this->assertCivilRecord($record, $registry);
            }
            $identifier = $this->civilIdentifier();
            self::assertNotNull($identifier, 'a civil register: civilIdentifier() names a record');
            $record = $this->answered(fn () => $omnistate->civilRecord($name, $identifier), 'civilRecord()');
            self::assertNotNull($record, 'civilRecord(): the recorded answers know civilIdentifier()');
            $this->assertCivilRecord($record, $registry);
            self::assertSame($identifier, $record->identifier, 'found by the identifier it carries');
        }
        if ($registry instanceof InternetRegistryInterface) {
            $domain = $this->answered(fn () => $omnistate->domain($this->domainName()), 'domain()');
            self::assertNotNull($domain, 'domain(): the recorded answers know domainName()');
            self::assertSame($name, $domain->source);
            self::assertSame(strtolower($this->domainName()), $domain->name);
            $network = $this->answered(fn () => $omnistate->network($this->networkAddress()), 'network()');
            self::assertNotNull($network, 'network(): the recorded answers know networkAddress()');
            self::assertSame($name, $network->source);
            self::assertNotSame('', $network->handle);
        }
    }

    /** @return array<string, \Closure(): mixed> each question the registry's contracts ask; lookups end with "(identifier)" */
    private function questions(object $registry, Omnistate $omnistate): array
    {
        $questions = [];
        if ($registry instanceof CompanyRegistryInterface) {
            $questions['company(identifier)'] = fn () => $omnistate->company($this->companyIdentifier());
            $questions['search()'] = fn () => $registry->search($this->searchQuery());
        }
        if ($registry instanceof VatRegistryInterface) {
            $questions['vat()'] = fn () => $omnistate->vat($this->vatNumber());
        }
        if ($registry instanceof ProfessionalRegistryInterface) {
            $questions['professional(identifier)'] = fn () => $omnistate->professional($this->professionalIdentifier());
            $questions['professionals()'] = fn () => $omnistate->professionals($this->searchQuery());
        }
        if ($registry instanceof FacilityRegistryInterface) {
            $questions['facility(identifier)'] = fn () => $omnistate->facility($this->facilityIdentifier());
        }
        if ($registry instanceof CivilRegistryInterface) {
            $query = $this->civilQuery();
            $identifier = $this->civilIdentifier();
            if (null !== $query) {
                $questions['civilRecords()'] = fn () => $omnistate->civilRecords($query, $registry->name());
            }
            if (null !== $identifier) {
                $questions['civilRecord(identifier)'] = fn () => $omnistate->civilRecord($registry->name(), $identifier);
            }
        }
        if ($registry instanceof InternetRegistryInterface) {
            $questions['domain(identifier)'] = fn () => $omnistate->domain($this->domainName());
            $questions['network(identifier)'] = fn () => $omnistate->network($this->networkAddress());
        }

        return $questions;
    }

    private function omnistate(object $registry): Omnistate
    {
        return new Omnistate(
            companies: $registry instanceof CompanyRegistryInterface ? [$registry] : [],
            vat: $registry instanceof VatRegistryInterface ? [$registry] : [],
            internet: $registry instanceof InternetRegistryInterface ? $registry : null,
            professionals: $registry instanceof ProfessionalRegistryInterface || $registry instanceof FacilityRegistryInterface ? [$registry] : [],
            civil: $registry instanceof CivilRegistryInterface ? [$registry] : [],
        );
    }

    /** The suite's HTTP client: the recorded answers (with $notFound, only infrastructureAnswers()), HTTP 404 for anything else. */
    private function contractHttp(bool $notFound = false): MockHttpClient
    {
        $answers = $notFound ? $this->infrastructureAnswers() : $this->recordedAnswers();
        $this->contractUnanswered = [];

        return new MockHttpClient(function (string $method, string $url, array $options) use ($answers): MockResponse {
            $bare = strtok($url, '?');
            $answer = $answers[$method.' '.$bare] ?? $answers[$method.' '.parse_url($url, \PHP_URL_PATH)] ?? $answers['*'] ?? null;
            if (\is_callable($answer)) {
                $answer = $answer($method, $url, $options);
            }
            if (\is_array($answer)) {
                $answer = new MockResponse(json_encode($answer, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
            }
            if (!$answer instanceof MockResponse) {
                $this->contractUnanswered[] = $method.' '.$url;

                return new MockResponse('{"message":"Not found"}', ['http_code' => 404]);
            }

            return $answer;
        });
    }

    /**
     * The answers a lookup needs before it reaches the record itself (IANA's bootstrap files,
     * which say which server to ask): kept when the suite answers HTTP 404 to the rest.
     *
     * @return array<string, array<mixed>|MockResponse|callable>
     */
    protected function infrastructureAnswers(): array
    {
        return [];
    }

    /**
     * @template T
     *
     * @param \Closure(): T $ask
     *
     * @return T
     */
    private function answered(\Closure $ask, string $question): mixed
    {
        try {
            return $ask();
        } catch (UnavailableException $e) {
            self::fail(\sprintf('%s on the recorded answers: %s%s', $question, $e->getMessage(), $this->contractUnanswered ? ' (no recorded answer for: '.implode(', ', $this->contractUnanswered).')' : ''));
        }
    }

    private function assertCompany(Company $company, string $source): void
    {
        self::assertSame($source, $company->source);
        self::assertNotSame('', $company->identifier);
        self::assertNotSame('', $company->name);
        self::assertMatchesRegularExpression('/^[A-Z]{2}$/', $company->country);
    }

    private function assertProfessional(Professional $professional, string $source): void
    {
        self::assertSame($source, $professional->source);
        self::assertNotSame('', $professional->identifier);
        self::assertNotSame('', $professional->familyName);
    }

    private function assertCivilRecord(CivilRecord $record, CivilRegistryInterface $registry): void
    {
        self::assertSame($registry->name(), $record->source);
        self::assertNotSame('', $record->identifier);
        self::assertContains($record->kind, $registry->kinds(), 'a record of a kind the register says it holds');
        if (null !== $record->url) {
            self::assertMatchesRegularExpression('#^https?://#', $record->url);
        }
    }
}
