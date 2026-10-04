<?php

namespace Omnistate\Model;

/**
 * A coded qualification as a register writes it: a profession, a specialty,
 * a diploma, a know-how (France: the ANS's nomenclatures, TRE_G15 for the
 * professions, TRE_R38 for the medical specialties...).
 */
final readonly class Qualification
{
    public function __construct(
        public string $code,
        /** The nomenclature: a URL or a short name ("TRE_G15-ProfessionSante"). */
        public string $system,
        public ?string $label = null,
    ) {
    }

    public function __toString(): string
    {
        return $this->label ?? $this->code;
    }
}
