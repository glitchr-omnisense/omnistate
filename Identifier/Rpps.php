<?php

namespace Omnistate\Identifier;

/**
 * A French health professional's RPPS number (Répertoire partagé des
 * professionnels de santé): eleven digits, the last a Luhn check digit. Its
 * national form, the IDNPS, is "8" followed by the RPPS. No register is
 * asked: a number can be well written and still belong to no one.
 */
final class Rpps
{
    /** "100 0346 1033" or the IDNPS "810003461033" -> "10003461033"; null when it is neither. */
    public static function normalize(?string $rpps): ?string
    {
        $rpps = (string) preg_replace('/[\s.\-]/', '', (string) $rpps);
        if (preg_match('/^8(\d{11})$/', $rpps, $match)) {
            return $match[1];
        }

        return preg_match('/^\d{11}$/', $rpps) ? $rpps : null;
    }

    /** Eleven digits whose last checks the ten others (Luhn). */
    public static function isValid(?string $rpps): bool
    {
        $rpps = self::normalize($rpps);

        return null !== $rpps && Luhn::isValid($rpps);
    }

    /** The national identifier the Annuaire Santé and Pro Santé Connect use: "8" + RPPS. */
    public static function toIdnps(string $rpps): string
    {
        return '8'.(self::normalize($rpps) ?? throw new \InvalidArgumentException(sprintf('"%s" is not an RPPS number.', $rpps)));
    }

    /** A valid number from its first ten digits (fixtures, tests): the Luhn key appended. */
    public static function withKey(string $tenDigits): string
    {
        if (!preg_match('/^\d{10}$/', $tenDigits)) {
            throw new \InvalidArgumentException('Ten digits expected.');
        }
        for ($key = 0; $key < 10; ++$key) {
            if (Luhn::isValid($tenDigits.$key)) {
                return $tenDigits.$key;
            }
        }

        throw new \LogicException('unreachable');
    }
}
