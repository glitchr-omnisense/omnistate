<?php

namespace Omnistate\Model;

/** Where an act was drawn up, where someone was born: as much as the register tells. */
final readonly class Place implements \Stringable
{
    public function __construct(
        /** The town, as the register writes it. */
        public ?string $name = null,
        /** The register's own code for it (France: the INSEE commune code). */
        public ?string $code = null,
        public ?string $postcode = null,
        /** A subdivision: a French département's code, a province. */
        public ?string $region = null,
        /** ISO 3166-1 alpha-2. */
        public ?string $country = null,
        /** The country as the register writes it, when it gives no code we know. */
        public ?string $countryName = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {
    }

    public function __toString(): string
    {
        return implode(', ', array_filter([$this->name, $this->countryName ?? $this->country], static fn (?string $part) => null !== $part && '' !== $part));
    }
}
