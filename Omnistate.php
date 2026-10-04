<?php

namespace Omnistate;

use Omnistate\Exception\NotSupportedException;
use Omnistate\Identifier\Finess;
use Omnistate\Identifier\Rpps;
use Omnistate\Identifier\Siren;
use Omnistate\Identifier\Siret;
use Omnistate\Identifier\VatNumber;
use Omnistate\Model\Company;
use Omnistate\Model\Domain;
use Omnistate\Model\Facility;
use Omnistate\Model\Network;
use Omnistate\Model\Professional;
use Omnistate\Model\VatCheck;
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
 */
final class Omnistate
{
    /** @var list<CompanyRegistryInterface> */
    private array $companies;
    /** @var list<VatRegistryInterface> */
    private array $vat;
    /** @var list<ProfessionalRegistryInterface> */
    private array $professionals;

    /**
     * @param iterable<CompanyRegistryInterface> $companies
     * @param iterable<VatRegistryInterface>     $vat
     * @param iterable<ProfessionalRegistryInterface> $professionals registers of regulated professionals (and their facilities)
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
    ) {
        $this->companies = [...$companies];
        $this->vat = [...$vat];
        $this->professionals = [...$professionals];
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
