<?php

namespace Omnistate\Sdmx;

use Omnistate\Model\ObservationStatus;

/**
 * An observation's flags as SDMX sources write them, read as one status:
 * SDMX's CL_OBS_STATUS (A normal, B break, E estimated, F forecast, I
 * imputed, M, L, H missing, N not significant, P provisional, Q suppressed,
 * R revised, U low reliability), INSEE's additions (O missing, SD
 * semi-final; OBS_QUAL's DEF, P, SD, E, F), Eurostat's lower-case letters
 * (p, e, s, f, b, c, u, n, r, d, z, one letter each). The most telling one
 * wins: confidential, missing, provisional, semi-final, estimated, forecast,
 * break, low reliability, not significant, revised, in that order.
 */
final class ObservationFlags
{
    private const CODES = [
        'C' => ObservationStatus::CONFIDENTIAL,
        'Q' => ObservationStatus::CONFIDENTIAL,
        'M' => ObservationStatus::MISSING,
        'L' => ObservationStatus::MISSING,
        'H' => ObservationStatus::MISSING,
        'O' => ObservationStatus::MISSING,
        'Z' => ObservationStatus::MISSING,
        'ND' => ObservationStatus::MISSING,
        'P' => ObservationStatus::PROVISIONAL,
        'SD' => ObservationStatus::SEMI_FINAL,
        'E' => ObservationStatus::ESTIMATED,
        'I' => ObservationStatus::ESTIMATED,
        'S' => ObservationStatus::ESTIMATED,
        'F' => ObservationStatus::FORECAST,
        'B' => ObservationStatus::BREAK,
        'U' => ObservationStatus::LOW_RELIABILITY,
        'N' => ObservationStatus::NOT_SIGNIFICANT,
        'R' => ObservationStatus::REVISED,
    ];

    private const ORDER = [
        ObservationStatus::CONFIDENTIAL,
        ObservationStatus::MISSING,
        ObservationStatus::PROVISIONAL,
        ObservationStatus::SEMI_FINAL,
        ObservationStatus::ESTIMATED,
        ObservationStatus::FORECAST,
        ObservationStatus::BREAK,
        ObservationStatus::LOW_RELIABILITY,
        ObservationStatus::NOT_SIGNIFICANT,
        ObservationStatus::REVISED,
    ];

    /**
     * @param list<string> $codes SDMX and INSEE codes as given (A, P, SD, DEF...); Eurostat's
     *                            flags split into letters first (letters())
     */
    public static function status(array $codes): ObservationStatus
    {
        $found = [];
        foreach ($codes as $code) {
            $status = self::CODES[strtoupper(trim($code))] ?? null;
            if (null !== $status) {
                $found[] = $status;
            }
        }
        foreach (self::ORDER as $status) {
            if (\in_array($status, $found, true)) {
                return $status;
            }
        }

        return ObservationStatus::NORMAL;
    }

    /**
     * Eurostat writes several flags together ("bp": a break, provisional): one code each.
     *
     * @return list<string>
     */
    public static function letters(string $flags): array
    {
        return array_values(array_filter(str_split(trim($flags)), static fn (string $letter): bool => '' !== trim($letter)));
    }
}
