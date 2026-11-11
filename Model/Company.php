<?php

namespace Omnistate\Model;

/**
 * A company as its register knows it. Whatever the source could not tell is
 * null or empty; $raw keeps its whole answer, for what this model leaves out.
 */
final readonly class Company
{
    public function __construct(
        /** The register's identifier (France: the SIREN). */
        public string $identifier,
        public string $name,
        public CompanyStatus $status,
        public string $country,
        /** Where it came from: "annuaire-entreprises", "inpi"... */
        public string $source,
        public ?string $legalName = null,
        public ?string $acronym = null,
        public ?LegalForm $legalForm = null,
        public ?Activity $activity = null,
        /** The size category (France: PME, ETI, GE) and its year. */
        public ?string $category = null,
        public ?Headcount $headcount = null,
        public ?\DateTimeImmutable $createdOn = null,
        public ?\DateTimeImmutable $closedOn = null,
        public ?Establishment $headOffice = null,
        /** @var list<Establishment> those the answer included (all, or the ones that matched) */
        public array $establishments = [],
        public ?int $establishmentCount = null,
        public ?int $openEstablishmentCount = null,
        /** @var list<Manager> */
        public array $managers = [],
        /** @var list<FinancialYear> newest first */
        public array $finances = [],
        /** @var list<string> what it is recognized as: "ess", "rge", "qualiopi"... */
        public array $labels = [],
        /** @var list<string> the collective agreements it applies (France: IDCC) */
        public array $collectiveAgreements = [],
        public ?string $vatNumber = null,
        public ?\DateTimeImmutable $updatedAt = null,
        /** @var array<mixed> the register's whole answer, as it was decoded */
        public array $raw = [],
    ) {
    }

    public function isActive(): bool
    {
        return CompanyStatus::ACTIVE === $this->status;
    }

    public function establishment(string $identifier): ?Establishment
    {
        foreach ([$this->headOffice, ...$this->establishments] as $establishment) {
            if ($establishment?->identifier === $identifier) {
                return $establishment;
            }
        }

        return null;
    }
}
