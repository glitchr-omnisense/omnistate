<?php

namespace Omnistate;

use Omnisense\Contracts\Policy\PolicyGuard;
use Omnisense\Contracts\Policy\RateLimiterInterface;
use Omnisense\Contracts\Policy\UsagePolicy;
use Omnisense\Contracts\Policy\UsageNotPermitted;
use Omnistate\Exception\NotSupportedException;
use Omnistate\Exception\OmnistateException;
use Omnistate\Exception\UnavailableException;
use Omnistate\Identifier\Finess;
use Omnistate\Identifier\Rpps;
use Omnistate\Identifier\Siren;
use Omnistate\Identifier\Siret;
use Omnistate\Identifier\VatNumber;
use Omnistate\Model\CivilQuery;
use Omnistate\Model\CivilRecord;
use Omnistate\Model\CivilRecordKind;
use Omnistate\Model\Dataset;
use Omnistate\Model\Company;
use Omnistate\Model\Domain;
use Omnistate\Model\Facility;
use Omnistate\Model\Network;
use Omnistate\Model\IndexChange;
use Omnistate\Model\Professional;
use Omnistate\Model\Series;
use Omnistate\Model\TimePeriod;
use Omnistate\Model\VatCheck;
use Omnistate\Policy\GuardedCivilRegistry;
use Omnistate\Policy\GuardedStatisticsRegistry;
use Omnistate\Policy\Guards;
use Omnistate\Registry\CivilRegistryInterface;
use Omnistate\Registry\CompanyRegistryInterface;
use Omnistate\Registry\FacilityRegistryInterface;
use Omnistate\Registry\InternetRegistryInterface;
use Omnistate\Registry\ProfessionalRegistryInterface;
use Omnistate\Registry\StatisticsRegistryInterface;
use Omnistate\Registry\VatRegistryInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * What states and registries know, asked of whichever registry installed
 * answers for it: a company by its identifier, a VAT number, a domain, a
 * network. Answers are kept $ttl seconds when a cache is given - registers
 * change slowly, and some count their calls.
 *
 * Every call to a register goes through its usage policy's guard (Policy\Guards):
 * the policy it declares (its policy.json), tightened by the site's
 * $policies, refused before it is made when a limit is reached or while the
 * register's 429 lasts (UsageLimitReachedException, an UnavailableException);
 * an answer kept in the cache is not a call. policies() lists them, with the
 * credit each one asks for.
 *
 *     $omnistate->company('901821074')?->name;            // "GLITCH ART"
 *     $omnistate->vat('FR53901821074')->valid;             // true
 *     $omnistate->domain('glitchr.dev')?->registrar;      // Gandi SAS
 *     $omnistate->professional('10003461033')?->profession; // Médecin (an RPPS number)
 *     $omnistate->facility('670001234')?->name;            // a FINESS number
 *     $omnistate->civilRecords(new CivilQuery(familyName: 'Chirac', born: Period::year(1932))); // acts and documents about a person
 *     $omnistate->series('insee:IRL', last: 4)?->latest()?->value;   // the rent reference index's last quarter
 */
final class Omnistate
{
    /** @var list<CompanyRegistryInterface> */
    private array $companies;
    /** @var list<VatRegistryInterface> */
    private array $vat;
    /** @var list<ProfessionalRegistryInterface> */
    private array $professionals;
    /** @var list<CivilRegistryInterface> */
    private array $civil;
    /** @var list<StatisticsRegistryInterface> */
    private array $statistics;
    private readonly Guards $guards;

    /**
     * @param iterable<CompanyRegistryInterface> $companies
     * @param iterable<VatRegistryInterface>     $vat
     * @param iterable<ProfessionalRegistryInterface> $professionals registers of regulated professionals (and their facilities)
     * @param iterable<CivilRegistryInterface>        $civil         registers of acts and documents about people (births, marriages, deaths, archives)
     * @param array<string, array<string, mixed>>     $policies      by register name: the site's tightening of its usage policy (rateLimits, rights, requirements), never looser
     * @param array<string, mixed>                    $usage         what the site is (commercial, open_source, contract): a use a register's terms forbid fails at once
     * @param RateLimiterInterface|null               $limiter       where the calls are counted; in memory when null, a shared one across processes
     * @param iterable<StatisticsRegistryInterface>   $statistics    sources of public statistics (INSEE, Eurostat, the ECB), asked in this order
     */
    public function __construct(
        iterable $companies = [],
        iterable $vat = [],
        private readonly ?InternetRegistryInterface $internet = null,
        private readonly ?CacheInterface $cache = null,
        private readonly int $ttl = 86400,
        /** Your own VAT number, sent with every check unless another is given: VIES then answers a consultation number. */
        private readonly ?string $requester = null,
        iterable $professionals = [],
        iterable $civil = [],
        array $policies = [],
        array $usage = [],
        ?RateLimiterInterface $limiter = null,
        iterable $statistics = [],
    ) {
        $this->companies = array_values([...$companies]);
        $this->vat = array_values([...$vat]);
        $this->professionals = array_values([...$professionals]);
        $this->civil = array_values([...$civil]);
        $this->statistics = array_values([...$statistics]);
        $this->guards = new Guards($policies, $usage, $limiter);
    }

    /**
     * A company by its identifier: a SIREN, a SIRET (its establishment among
     * its establishments), a French VAT number (its SIREN)...
     *
     * @throws NotSupportedException no registry installed knows that kind of identifier
     * @throws UnavailableException  the register did not answer, or its usage policy holds the call (UsageLimitReachedException)
     * @throws UsageNotPermitted     the register's terms do not allow the site's use
     */
    public function company(string $identifier): ?Company
    {
        $identifier = self::identifier($identifier);
        foreach ($this->companies as $registry) {
            if ($registry->supports($identifier)) {
                return $this->remember('company.'.$registry->name().'.'.$identifier, $registry, 'company', static fn () => $registry->company($identifier));
            }
        }

        throw new NotSupportedException(sprintf('No company registry installed knows "%s".', $identifier));
    }

    /**
     * @return list<Company> from every company registry installed, the first that finds any
     *
     * @throws UnavailableException a register did not answer, or its usage policy holds the call
     */
    public function search(string $query, int $limit = 10): array
    {
        foreach ($this->companies as $registry) {
            if ($found = $this->remember('search.'.$registry->name().'.'.$limit.'.'.mb_strtolower(trim($query)), $registry, 'search', static fn () => $registry->search($query, $limit))) {
                return $found;
            }
        }

        return [];
    }

    /**
     * Whether a VAT number exists, and whose. A number no member state writes
     * that way is not valid, without asking anyone.
     *
     * @param string|null $requester your own VAT number, for a consultation number
     *
     * @throws NotSupportedException no VAT registry installed answers for its country
     * @throws UnavailableException  the register did not answer, or its usage policy holds the call
     */
    public function vat(string $number, ?string $requester = null): VatCheck
    {
        $normalized = VatNumber::normalize($number);
        if (null === $normalized || !VatNumber::isWellFormed($normalized)) {
            return new VatCheck(strtoupper((string) preg_replace('/\s/', '', $number)), false, new \DateTimeImmutable(), 'format');
        }
        $requester = VatNumber::normalize($requester ?? $this->requester);
        foreach ($this->vat as $registry) {
            if ($registry->supports($normalized)) {
                return $this->remember('vat.'.$registry->name().'.'.$normalized.'.'.$requester, $registry, 'check', static fn () => $registry->check($normalized, $requester));
            }
        }

        throw new NotSupportedException(sprintf('No VAT registry installed answers for %s.', VatNumber::country($normalized)));
    }

    /**
     * A regulated professional by their identifier: an RPPS number (or its
     * IDNPS form, "8" + RPPS)...
     *
     * @throws NotSupportedException no professional registry installed knows that kind of identifier
     * @throws UnavailableException  the register did not answer, or its usage policy holds the call
     */
    public function professional(string $identifier): ?Professional
    {
        $identifier = (string) preg_replace('/[\s.\-]/', '', $identifier);
        $identifier = Rpps::normalize($identifier) ?? $identifier;
        foreach ($this->professionals as $registry) {
            if ($registry->supports($identifier)) {
                return $this->remember('professional.'.$registry->name().'.'.$identifier, $registry, 'professional', static fn () => $registry->professional($identifier));
            }
        }

        throw new NotSupportedException(sprintf('No professional registry installed knows "%s".', $identifier));
    }

    /** @return list<Professional> from every professional registry installed, the first that finds any */
    public function professionals(string $name, ?string $postcode = null, int $limit = 10): array
    {
        foreach ($this->professionals as $registry) {
            if ($found = $this->remember('professionals.'.$registry->name().'.'.$limit.'.'.$postcode.'.'.mb_strtolower(trim($name)), $registry, 'search', static fn () => $registry->search($name, $postcode, $limit))) {
                return $found;
            }
        }

        return [];
    }

    /**
     * A health or social facility by its identifier (a FINESS number), asked
     * of the professional registries that know facilities too.
     *
     * @throws NotSupportedException
     */
    public function facility(string $identifier): ?Facility
    {
        $identifier = Finess::normalize($identifier) ?? strtoupper((string) preg_replace('/[\s.\-]/', '', $identifier));
        foreach ($this->professionals as $registry) {
            if ($registry instanceof FacilityRegistryInterface && $registry->supportsFacility($identifier)) {
                return $this->remember('facility.'.$registry->name().'.'.$identifier, $registry, 'facility', static fn () => $registry->facility($identifier));
            }
        }

        throw new NotSupportedException(sprintf('No facility registry installed knows "%s".', $identifier));
    }

    /**
     * The civil registers installed, those that hold records of that country
     * (ISO 3166-1 alpha-2) and of that kind when given; each behind its
     * usage policy's guard (search() and find() counted).
     *
     * @return list<CivilRegistryInterface>
     */
    public function civilRegistries(?string $country = null, ?CivilRecordKind $kind = null): array
    {
        $country = null === $country ? null : strtoupper(trim($country));

        return array_values(array_map(fn (CivilRegistryInterface $registry): CivilRegistryInterface => new GuardedCivilRegistry($registry, $this->guards), array_filter($this->civil, static fn (CivilRegistryInterface $registry) => (null === $country || \in_array($country, $registry->countries(), true))
            && (null === $kind || \in_array($kind, $registry->kinds(), true)))));
    }

    /** The civil register of that name, behind its guard; null when it is not installed. */
    public function civilRegistry(string $name): ?CivilRegistryInterface
    {
        foreach ($this->civil as $registry) {
            if ($registry->name() === $name) {
                return new GuardedCivilRegistry($registry, $this->guards);
            }
        }

        return null;
    }

    /** @return list<string> ISO 3166-1 alpha-2 codes of the countries some civil register installed covers, sorted */
    public function civilCountries(): array
    {
        $countries = [];
        foreach ($this->civil as $registry) {
            foreach ($registry->countries() as $country) {
                $countries[$country] = true;
            }
        }
        ksort($countries);

        return array_keys($countries);
    }

    /**
     * Acts and documents about a person. Asked of one register when it is
     * named, else of every register installed the question is one for - their
     * answers one after the other, in the order the registers were given.
     *
     * A register that does not answer throws: asking them all, one that is
     * down takes the others' answers with it. A screen that must keep what
     * the others found asks them one by one (civilRegistries(), then this
     * with each name).
     *
     * @return list<CivilRecord>
     *
     * @throws NotSupportedException                 no civil registry installed takes that question
     * @throws \Omnistate\Exception\UnavailableException a register did not answer
     */
    public function civilRecords(CivilQuery $query, ?string $registry = null): array
    {
        $asked = false;
        $records = [];
        foreach ($this->civil as $candidate) {
            if ((null !== $registry && $candidate->name() !== $registry) || !$candidate->supports($query)) {
                continue;
            }
            $asked = true;
            $found = $this->remember('civil.search.'.$candidate->name().'.'.$query->key(), $candidate, 'search', static fn () => $candidate->search($query));
            array_push($records, ...$found);
        }
        if (!$asked) {
            throw new NotSupportedException(null === $registry ? 'No civil registry installed takes that question.' : sprintf('The civil registry "%s" is not installed, or does not take that question.', $registry));
        }

        return $records;
    }

    /**
     * A civil record found again: the register's name (CivilRecord::$source)
     * and its identifier there (CivilRecord::$identifier). Often more complete
     * than the line a search gave: everyone named in the act, its pictures.
     *
     * @throws NotSupportedException that register is not installed
     */
    public function civilRecord(string $registry, string $identifier): ?CivilRecord
    {
        $found = $this->registry($registry, $this->civil) ?? throw new NotSupportedException(sprintf('The civil registry "%s" is not installed.', $registry));

        return $this->remember('civil.record.'.$registry.'.'.$identifier, $found, 'find', static fn () => $found->find($identifier));
    }

    /**
     * A statistical series by its ref: "<source>:<id>" ("insee:IRL",
     * "ecb:EXR/D.USD.EUR.SP00.A", "eurostat:prc_hicp_manr?geo=FR&coicop=CP00&unit=RCH_A&freq=M"),
     * or an id alone, asked of the first source that reads it. Its
     * observations from $from to $to (both included, either open), or its
     * $last ones; with $history, every vintage of every value. Null when
     * the source does not know it.
     *
     * @throws NotSupportedException no source installed reads that id (or keeps history, when asked)
     * @throws UnavailableException  the source did not answer, or its usage policy holds the call
     */
    public function series(string $ref, ?TimePeriod $from = null, ?TimePeriod $to = null, ?int $last = null, bool $history = false): ?Series
    {
        [$registry, $id] = $this->statisticsFor($ref);

        return $this->remember('series.'.$registry->name().'.'.$id.'.'.$from.'.'.$to.'.'.$last.'.'.(int) $history, $registry, 'series', static fn () => $registry->series($id, $from, $to, $last, $history));
    }

    /**
     * How an index moved between two periods (an IRL a year apart): the
     * series asked over them, then IndexChange::between().
     *
     * @throws NotSupportedException no source installed reads that id
     * @throws OmnistateException     the series is unknown, or holds no value for one of the periods
     * @throws UnavailableException   the source did not answer
     */
    public function indexChange(string $ref, TimePeriod $from, TimePeriod $to): IndexChange
    {
        [$first, $second] = $from->compare($to) <= 0 ? [$from, $to] : [$to, $from];
        $series = $this->series($ref, $first, $second) ?? throw new OmnistateException(sprintf('No series "%s".', $ref));

        return IndexChange::between($series, $from, $to);
    }

    /**
     * Datasets whose title holds the query's words, from one source when
     * named, else from every source installed, one after the other.
     *
     * @return list<Dataset>
     *
     * @throws NotSupportedException that source is not installed
     * @throws UnavailableException  a source did not answer
     */
    public function datasets(string $query, ?string $source = null, int $limit = 10): array
    {
        $found = [];
        foreach ($this->statistics as $registry) {
            if (null !== $source && $registry->name() !== $source) {
                continue;
            }
            array_push($found, ...$this->remember('datasets.'.$registry->name().'.'.$limit.'.'.mb_strtolower(trim($query)), $registry, 'datasets', static fn () => $registry->datasets($query, $limit)));
        }
        if (null !== $source && null === $this->registry($source, $this->statistics)) {
            throw new NotSupportedException(sprintf('The statistics source "%s" is not installed.', $source));
        }

        return $found;
    }

    /**
     * The statistics sources installed, each behind its usage policy's guard.
     *
     * @return list<StatisticsRegistryInterface>
     */
    public function statisticsRegistries(): array
    {
        return array_map(fn (StatisticsRegistryInterface $registry): StatisticsRegistryInterface => new GuardedStatisticsRegistry($registry, $this->guards), $this->statistics);
    }

    /** The statistics source of that name, behind its guard; null when it is not installed. */
    public function statisticsRegistry(string $name): ?StatisticsRegistryInterface
    {
        $registry = $this->registry($name, $this->statistics);

        return null === $registry ? null : new GuardedStatisticsRegistry($registry, $this->guards);
    }

    public function domain(string $name): ?Domain
    {
        $name = rtrim(mb_strtolower(trim($name)), '.');

        $internet = $this->internet();

        return $this->remember('domain.'.$name, $internet, 'domain', static fn () => $internet->domain($name));
    }

    /** An IP address's range, or an AS number's. */
    public function network(string $address): ?Network
    {
        $address = strtoupper(trim($address));

        $internet = $this->internet();

        return $this->remember('network.'.$address, $internet, 'network', static fn () => $internet->network($address));
    }

    /**
     * A register's usage policy as the site runs it: what it declares
     * (limits, rights, the credit to show), tightened by the site's.
     *
     * @throws NotSupportedException no register of that name is installed
     */
    public function policy(string $name): UsagePolicy
    {
        return $this->guard($name)->policy();
    }

    /**
     * Every installed register's policy, by name: what a site's "sources of
     * the data we show" page lists (each policy's rights->attribution).
     *
     * @return array<string, UsagePolicy>
     */
    public function policies(): array
    {
        $policies = [];
        foreach ($this->registries() as $name => $registry) {
            $policies[$name] = $this->guards->guard($name, $registry)->policy();
        }

        return $policies;
    }

    /**
     * A register's guard: its policy, the calls left (remaining()), the time
     * to live its terms allow an answer (cacheTtl()).
     *
     * @throws NotSupportedException no register of that name is installed
     */
    public function guard(string $name): PolicyGuard
    {
        $registry = $this->registries()[$name] ?? throw new NotSupportedException(sprintf('No registry "%s" is installed.', $name));

        return $this->guards->guard($name, $registry);
    }

    /** A French VAT number is looked up by its SIREN; spaces, dots and dashes go. */
    private static function identifier(string $identifier): string
    {
        $clean = strtoupper((string) preg_replace('/[\s.\-]/', '', $identifier));
        if (preg_match('/^FR[0-9A-Z]{2}(\d{9})$/', $clean, $match) && Siren::isValid($match[1])) {
            return $match[1];
        }

        return Siret::normalize($clean) ?? Siren::normalize($clean) ?? $clean;
    }

    /** @return array<string, object> every register installed, by name, the first of a name kept */
    private function registries(): array
    {
        $registries = [];
        foreach ([...$this->companies, ...$this->vat, ...$this->professionals, ...$this->civil, ...$this->statistics, ...(null === $this->internet ? [] : [$this->internet])] as $registry) {
            $registries[$registry->name()] ??= $registry;
        }

        return $registries;
    }

    /**
     * @template T of CivilRegistryInterface|StatisticsRegistryInterface
     *
     * @param list<T> $registries
     *
     * @return T|null
     */
    private function registry(string $name, array $registries): ?object
    {
        foreach ($registries as $registry) {
            if ($registry->name() === $name) {
                return $registry;
            }
        }

        return null;
    }

    /**
     * The source a ref names ("insee:IRL"), or the first that reads an id given alone.
     *
     * @return array{StatisticsRegistryInterface, string}
     */
    private function statisticsFor(string $ref): array
    {
        $ref = trim($ref);
        if (1 === preg_match('/^([a-z0-9]+(?:-[a-z0-9]+)*):(.+)$/', $ref, $match) && null !== $registry = $this->registry($match[1], $this->statistics)) {
            return $registry->supports($match[2]) ? [$registry, $match[2]] : throw new NotSupportedException(sprintf('The statistics source "%s" does not read "%s".', $match[1], $match[2]));
        }
        foreach ($this->statistics as $registry) {
            if ($registry->supports($ref)) {
                return [$registry, $ref];
            }
        }

        throw new NotSupportedException(sprintf('No statistics source installed reads "%s".', $ref));
    }

    private function internet(): InternetRegistryInterface
    {
        return $this->internet ?? throw new NotSupportedException('No Internet registry installed: add omnistate/iana.');
    }

    /**
     * The answer kept, or the register asked under its guard - an answer
     * kept is not a call. It is kept $ttl seconds, or less when the
     * register's terms say so (0: not kept at all).
     *
     * @template T
     *
     * @param callable(): T $ask
     *
     * @return T
     */
    private function remember(string $key, CompanyRegistryInterface|VatRegistryInterface|ProfessionalRegistryInterface|FacilityRegistryInterface|CivilRegistryInterface|InternetRegistryInterface|StatisticsRegistryInterface $registry, string $operation, callable $ask): mixed
    {
        $name = $registry->name();
        $call = fn () => $this->guards->call($name, $registry, $operation, $ask);
        $ttl = null === $this->cache ? 0 : ($this->guards->guard($name, $registry)->cacheTtl($this->ttl) ?? $this->ttl);
        if (null === $this->cache || $ttl <= 0) {
            return $call();
        }

        return $this->cache->get('omnistate.'.hash('xxh128', $key), static function (ItemInterface $item) use ($call, $ttl) {
            $item->expiresAfter($ttl);

            return $call();
        });
    }
}
