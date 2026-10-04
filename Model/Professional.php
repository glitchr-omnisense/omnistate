<?php

namespace Omnistate\Model;

/**
 * A regulated professional as their register knows them: a physician, a
 * nurse, a physiotherapist (France: the RPPS, through the Annuaire Santé);
 * later a lawyer (the CNB's directory). Whatever the source could not tell
 * is null or empty; $raw keeps its whole answer.
 */
final readonly class Professional
{
    public function __construct(
        /** The register's identifier (France: the RPPS number). */
        public string $identifier,
        public string $familyName,
        /** Where it came from: "annuaire-sante", "cnb"... */
        public string $source,
        public ?string $givenName = null,
        /** "M", "MME", "DR"... as the register writes it. */
        public ?string $prefix = null,
        public ?Qualification $profession = null,
        /** @var list<Qualification> specialties and know-how */
        public array $specialties = [],
        /** @var list<Qualification> diplomas */
        public array $diplomas = [],
        /** @var list<Workplace> where they practise, active ones first */
        public array $workplaces = [],
        /** @var list<string> */
        public array $phones = [],
        /** @var list<string> */
        public array $emails = [],
        /** @var list<string> secure messaging (France: MSSanté addresses) */
        public array $secureEmails = [],
        /** @var list<string> ISO 639-1 */
        public array $languages = [],
        public bool $active = true,
        public ?\DateTimeImmutable $updatedAt = null,
        public array $raw = [],
    ) {
    }

    /** "Arthur Saucier" */
    public function name(): string
    {
        return trim(($this->givenName ?? '').' '.$this->familyName);
    }

    public function __toString(): string
    {
        return $this->name();
    }
}
