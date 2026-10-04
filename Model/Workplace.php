<?php

namespace Omnistate\Model;

/**
 * Where and how a professional practises: a practice, a health centre, a
 * hospital - its name and address, the facility's identifier when it has
 * one (France: its FINESS), the mode of practice (self-employed, salaried)
 * and the role held there.
 */
final readonly class Workplace
{
    public function __construct(
        public ?string $name = null,
        public ?Address $address = null,
        /** The facility's own identifier (France: FINESS, or the register's structure id). */
        public ?string $facility = null,
        /** How: "liberal", "salaried", or the register's own code. */
        public ?string $mode = null,
        public ?string $role = null,
        /** @var list<string> */
        public array $phones = [],
        /** @var list<string> secure messaging addresses of that place (France: MSSanté) */
        public array $secureEmails = [],
        public bool $active = true,
    ) {
    }

    public function __toString(): string
    {
        return trim(implode(', ', array_filter([$this->name, $this->address ? (string) $this->address : null])));
    }
}
