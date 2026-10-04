<?php

namespace Omnistate\Model;

/**
 * A health or social facility as its register knows it (France: FINESS,
 * through the Annuaire Santé): a hospital, a health centre, a pharmacy, a
 * multi-professional practice.
 */
final readonly class Facility
{
    public function __construct(
        /** The register's identifier (France: the FINESS number). */
        public string $identifier,
        public string $name,
        public string $source,
        /** "legal" (the entity) or "site" (one of its places), when the register says. */
        public ?string $kind = null,
        /** @var list<Qualification> its categories: sector, activity... */
        public array $types = [],
        public ?Address $address = null,
        /** @var list<string> */
        public array $phones = [],
        /** @var list<string> */
        public array $emails = [],
        /** @var list<string> */
        public array $secureEmails = [],
        /** @var list<string> other identifiers the register gives it */
        public array $identifiers = [],
        public bool $active = true,
        public ?\DateTimeImmutable $updatedAt = null,
        public array $raw = [],
    ) {
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
