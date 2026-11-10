<?php

namespace Omnistate\Model;

/**
 * How often a statistical series is measured: SDMX's FREQ codes (CL_FREQ),
 * the period each of its observations stands for.
 */
enum Frequency: string
{
    case ANNUAL = 'A';
    case SEMIANNUAL = 'S';
    case QUARTERLY = 'Q';
    case MONTHLY = 'M';
    case WEEKLY = 'W';
    case DAILY = 'D';
    /** Business days (the ECB's exchange rates): each observation is a day. */
    case BUSINESS = 'B';

    /**
     * A source's code: SDMX's (A, S, Q, M, W, D, B), or INSEE's French one
     * for quarters (T, trimestriel); null when it is none of them.
     */
    public static function fromCode(?string $code): ?self
    {
        $code = strtoupper(trim((string) $code));

        return 'T' === $code ? self::QUARTERLY : self::tryFrom($code);
    }

    /** How many periods a year holds; null for days, whose number varies. */
    public function perYear(): ?int
    {
        return match ($this) {
            self::ANNUAL => 1,
            self::SEMIANNUAL => 2,
            self::QUARTERLY => 4,
            self::MONTHLY => 12,
            self::WEEKLY, self::DAILY, self::BUSINESS => null,
        };
    }
}
