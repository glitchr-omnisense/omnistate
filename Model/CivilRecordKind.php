<?php

namespace Omnistate\Model;

/**
 * What a civil record records. OTHER is everything a register holds about a
 * person that is not one of the five acts: a population register, a notarial
 * deed, a catalogue entry of an archive.
 */
enum CivilRecordKind: string
{
    case BIRTH = 'birth';
    case BAPTISM = 'baptism';
    case MARRIAGE = 'marriage';
    case DEATH = 'death';
    case BURIAL = 'burial';
    case OTHER = 'other';

    /** The GEDCOM tag of the event it documents (BIRT, CHR, MARR, DEAT, BURI), null for OTHER. */
    public function gedcom(): ?string
    {
        return match ($this) {
            self::BIRTH => 'BIRT',
            self::BAPTISM => 'CHR',
            self::MARRIAGE => 'MARR',
            self::DEATH => 'DEAT',
            self::BURIAL => 'BURI',
            self::OTHER => null,
        };
    }
}
