<?php

namespace Omnistate\Model;

/** Who keeps the original, and under which reference: what a citation needs. */
final readonly class Archive implements \Stringable
{
    public function __construct(
        /** "Brabants Historisch Informatie Centrum", "INSEE", "The National Archives, Kew". */
        public string $name,
        /** The register's code for it ("bhi"). */
        public ?string $code = null,
        /** The call number: fonds, register, folio, act number. */
        public ?string $reference = null,
        /** The record at the archive itself. */
        public ?string $url = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->name.(null !== $this->reference && '' !== $this->reference ? ', '.$this->reference : '');
    }
}
