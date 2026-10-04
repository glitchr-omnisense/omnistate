<?php

namespace Omnistate\Model;

/**
 * Someone named in a civil record: the child and both parents of a birth,
 * the spouses of a marriage, the deceased. Whatever the register does not
 * tell is null.
 */
final readonly class CivilPerson implements \Stringable
{
    public function __construct(
        public string $familyName = '',
        /** @var list<string> */
        public array $givenNames = [],
        public Sex $sex = Sex::UNKNOWN,
        /** As the register writes it: "Child", "Father", "Bride", "Deceased", "Witness"... */
        public ?string $role = null,
        public ?PartialDate $birthDate = null,
        public ?Place $birthPlace = null,
        public ?PartialDate $deathDate = null,
        public ?Place $deathPlace = null,
        /** At the event, in years. */
        public ?int $age = null,
        public ?string $profession = null,
        /** The whole name, when the register gives it in one piece only. */
        public ?string $fullName = null,
    ) {
    }

    /** "Jacques René Chirac" */
    public function name(): string
    {
        $name = trim(implode(' ', $this->givenNames).' '.$this->familyName);

        return '' !== $name ? $name : (string) $this->fullName;
    }

    public function givenName(): ?string
    {
        return $this->givenNames[0] ?? null;
    }

    public function __toString(): string
    {
        return $this->name();
    }
}
