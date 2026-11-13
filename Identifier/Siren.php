<?php

namespace Omnistate\Identifier;

/**
 * A French company's SIREN: nine digits, the last a Luhn check digit. No
 * register is asked: a SIREN can be well written and still belong to no one.
 */
final class Siren
{
    /** "552 032 534" -> "552032534"; null when it is not nine digits. */
    public static function normalize(?string $siren): ?string
    {
        $siren = preg_replace('/[\s.\-]/', '', (string) $siren);

        return preg_match('/^\d{9}$/', (string) $siren) ? $siren : null;
    }

    public static function isValid(?string $siren): bool
    {
        $siren = self::normalize($siren);

        return null !== $siren && Luhn::isValid($siren);
    }

    /** The French intra-EU VAT number of that SIREN: FR + ((12 + 3 × (SIREN mod 97)) mod 97) + SIREN. */
    public static function toVatNumber(string $siren): string
    {
        $siren = self::normalize($siren) ?? throw new \InvalidArgumentException(sprintf('"%s" is not a SIREN.', $siren));

        return sprintf('FR%02d%s', (12 + 3 * ((int) $siren % 97)) % 97, $siren);
    }
}
