<?php

namespace Omnistate\Model;

enum Sex: string
{
    case MALE = 'M';
    case FEMALE = 'F';
    case UNKNOWN = 'U';

    /** From what registers write: "M", "F", "male", "Man", "Vrouw", "1", "2"... */
    public static function fromAny(mixed $value): self
    {
        return match (mb_strtolower(trim((string) $value))) {
            'm', 'male', 'man', 'h', 'homme', 'masculin', '1' => self::MALE,
            'f', 'female', 'vrouw', 'femme', 'féminin', 'feminin', 'w', '2' => self::FEMALE,
            default => self::UNKNOWN,
        };
    }
}
