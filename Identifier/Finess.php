<?php

namespace Omnistate\Identifier;

/**
 * A French health or social establishment's FINESS number: nine characters,
 * the department first (two digits, or 2A / 2B for Corsica), the last a Luhn
 * check digit over the eight before it. A legal entity (FINESS juridique) and
 * each of its sites (FINESS géographique) have one each.
 */
final class Finess
{
    /** "67 000 123 4" -> "670001234"; null when it is not nine characters of that shape. */
    public static function normalize(?string $finess): ?string
    {
        $finess = strtoupper((string) preg_replace('/[\s.\-]/', '', (string) $finess));

        return preg_match('/^(\d{2}|2A|2B)\d{7}$/', $finess) ? $finess : null;
    }

    /**
     * Nine characters whose last checks the eight before it (Luhn). Corsica's
     * letters count as 0 (2A) and 1 (2B), the way the register computes its key.
     */
    public static function isValid(?string $finess): bool
    {
        $finess = self::normalize($finess);

        return null !== $finess && Luhn::isValid(strtr($finess, ['A' => '0', 'B' => '1']));
    }

    /** The department the number was given in: "67", "2A". */
    public static function department(string $finess): ?string
    {
        return null === ($finess = self::normalize($finess)) ? null : substr($finess, 0, 2);
    }
}
