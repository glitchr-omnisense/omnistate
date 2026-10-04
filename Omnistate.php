<?php

namespace Omnistate;

use Omnistate\Exception\NotSupportedException;
use Omnistate\Identifier\Finess;
use Omnistate\Identifier\Rpps;
use Omnistate\Identifier\Siren;
use Omnistate\Identifier\Siret;
use Omnistate\Identifier\VatNumber;
use Omnistate\Model\CivilQuery;
use Omnistate\Model\CivilRecord;
use Omnistate\Model\CivilRecordKind;
use Omnistate\Model\Company;
use Omnistate\Model\Domain;
use Omnistate\Model\Facility;
use Omnistate\Model\Network;
use Omnistate\Model\Professional;
use Omnistate\Model\VatCheck;
use Omnistate\Registry\CivilRegistryInterface;
use Omnistate\Registry\CompanyRegistryInterface;
use Omnistate\Registry\FacilityRegistryInterface;
use Omnistate\Registry\InternetRegistryInterface;
use Omnistate\Registry\ProfessionalRegistryInterface;
use Omnistate\Registry\VatRegistryInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * What states and registries know, asked of whichever registry installed
 * answers for it: a company by its identifier, a VAT number, a domain, a
 * network. Answers are kept $ttl seconds when a cache is given - registers
 * change slowly, and some count their calls.
 *
 *     $omnistate->company('901821074')?->name;            // "GLITCH ART"
 *     $omnistate->vat('FR53901821074')->valid;             // true
 *     $omnistate->domain('glitchr.dev')?->registrar;      // Gandi SAS
 *     $omnistate->professional('10003461033')?->profession; // Médecin (an RPPS number)
 *     $omnistate->facility('670001234')?->name;            // a FINESS number
 *     $omnistate->civilRecords(new CivilQuery(familyName: 'Chirac', born: Period::year(1932))); // acts and documents about a person
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

    /**
     * @param iterable<CompanyRegistryInterface> $companies
     * @param iterable<VatRegistryInterface>     $vat
     * @param iterable<ProfessionalRegistryInterface> $professionals registers of regulated professionals (and their facilities)
     * @param iterable<CivilRegistryInterface>        $civil         registers of acts and documents about people (births, marriages, deaths, archives)
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
    ) {
        $this->companies = [...$companies];
        $this->vat = [...$vat];
        $this->professionals = [...$professionals];
        $this->civil = [...$civil];
    }

    /**
     * A company by its identifier: a SIREN, a SIRET (its establishment among
     * its establishments), a French VAT number (its SIREN)...
     *
     * @throws NotSupportedException no registry installed knows that kind of identifier
     */
    public function company(string $identifier): ?Company
    {
        $identifier = self::identifier($identifier);
        foreach ($this->companies as $registry) {
            if ($registry->supports($identifier)) {
                return $this->remember('company.'.$registry->name().'.'.$identifier, fn () => $registry->company($identifier));
            }
        }

        throw new NotSupportedException(sprintf('No company registry installed knows "%s".', $identifier));
    }

    /** @return list<Company> from every company registry installed, the first that finds any */
    public function search(string $query, int $limit = 10): array
    {
        foreach ($this->companies as $registry) {
            if ($found = $this->remember('search.'.$registry->name().'.'.$limit.'.'.mb_strtolower(trim($query)), fn () => $registry->search($query, $limit))) {
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
     */
    public function vat(string $number, ?string $requester = null): VatCheck
    {
        $normalized = VatNumber::normalize($number);
        if (null === $normalized || !VatNumber::isWellFormed($normalized)) {
            return new VatCheck(strtoupper(preg_replace('/\s/', '', $number)), false, new \DateTimeImmutable(), 'format');
        }
        $requester = VatNumber::normalize($requester ?? $this->requester);
        foreach ($this->vat as $registry) {
            if ($registry->supports($normalized)) {
                return $this->remember('vat.'.$registry->name().'.'.$normalized.'.'.$requester, fn () => $registry->check($normalized, $requester));
            }
        }

        throw new NotSupportedException(sprintf('No VAT registry installed answers for %s.', VatNumber::country($normalized)));
    }

    /**
     * A regulated professional by their identifier: an RPPS number (or its
     * IDNPS form, "8" + RPPS)...
     *
     * @throws NotSupportedException no professional registry installed knows that kind of identifier
     */
    public function professional(string $identifier): ?Professional
    {
        $identifier = (string) preg_replace('/[\s.\-]/', '', $identifier);
        $identifier = Rpps::normalize($identifier) ?? $identifier;
        foreach ($this->professionals as $registry) {
            if ($registry->supports($identifier)) {
                return $this->remember('professional.'.$registry->name().'.'.$identifier, fn () => $registry->professional($identifier));
            }
        }

        throw new NotSupportedException(sprintf('No professional registry installed knows "%s".', $identifier));
    }

    /** @return list<Professional> from every professional registry installed, the first that finds any */
    public function professionals(string $name, ?string $postcode = null, int $limit = 10): array
    {
        foreach ($this->professionals as $registry) {
            if ($found = $this->remember('professionals.'.$registry->name().'.'.$limit.'.'.$postcode.'.'.mb_strtolower(trim($name)), fn () => $registry->search($name, $postcode, $limit))) {
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
                return $this->remember('facility.'.$registry->name().'.'.$identifier, fn () => $registry->facility($identifier));
            }
        }

        throw new NotSupportedException(sprintf('No facility registry installed knows "%s".', $identifier));
    }

    /**
     * The civil registers installed, those that hold records of that country
     * (ISO 3166-1 alpha-2) and of that kind when given.
     *
     * @return list<CivilRegistryInterface>
     */
    public function civilRegistries(?string $country = null, ?CivilRecordKind $kind = null): array
    {
        $country = null === $country ? null : strtoupper(trim($country));

        return array_values(array_filter($this->civil, static fn (CivilRegistryInterface $registry) => (null === $country || \in_array($country, $registry->countries(), true))
            && (null === $kind || \in_array($kind, $registry->kinds(), true))));
    }

    /** The civil register of that name, null when it is not installed. */
    public function civilRegistry(string $name): ?CivilRegistryInterface
    {
        foreach ($this->civil as $registry) {
            if ($registry->name() === $name) {
                return $registry;
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
            $found = $this->remember('civil.search.'.$candidate->name().'.'.$query->key(), static fn () => $candidate->search($query));
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
        $found = $this->civilRegistry($registry) ?? throw new NotSupportedException(sprintf('The civil registry "%s" is not installed.', $registry));

        return $this->remember('civil.record.'.$registry.'.'.$identifier, static fn () => $found->find($identifier));
    }

    public function domain(string $name): ?Domain
    {
        $name = rtrim(mb_strtolower(trim($name)), '.');

        return $this->remember('domain.'.$name, fn () => $this->internet()->domain($name));
    }

    /** An IP address's range, or an AS number's. */
    public function network(string $address): ?Network
    {
        $address = strtoupper(trim($address));

        return $this->remember('network.'.$address, fn () => $this->internet()->network($address));
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

    private function internet(): InternetRegistryInterface
    {
        return $this->internet ?? throw new NotSupportedException('No Internet registry installed: add omnistate/iana.');
    }

    /**
     * @template T
     *
     * @param callable(): T $ask
     *
     * @return T
     */
    private function remember(string $key, callable $ask): mixed
    {
        if (null === $this->cache) {
            return $ask();
        }

        return $this->cache->get('omnistate.'.hash('xxh128', $key), function (ItemInterface $item) use ($ask) {
            $item->expiresAfter($this->ttl);

            return $ask();
        });
    }
}
