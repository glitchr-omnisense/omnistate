<?php

namespace Omnistate\Model;

use Omnistate\Exception\OmnistateException;

/**
 * The period one statistical observation stands for: a year, a half-year,
 * a quarter, a month, an ISO week or a day. Written as SDMX writes it (ISO
 * 8601 and SDMX's reporting periods): "2026", "2026-S1", "2026-Q3",
 * "2026-09", "2026-W05", "2026-11-09".
 *
 *     TimePeriod::quarter(2026, 2);          // 2026-Q2: the IRL a lease of April 2026 refers to
 *     TimePeriod::parse('2026-Q2')->start(); // 2026-04-01
 *     TimePeriod::parse('2026-Q2')->shift(-4); // 2025-Q2: a year before
 *
 * Periods of one frequency compare and count in their unit (shift(),
 * next(), previous(), compare()); comparing two frequencies compares their
 * starts, then their ends.
 */
final readonly class TimePeriod implements \Stringable
{
    /** @param int $number the half-year (1-2), quarter (1-4), month (1-12) or ISO week (1-53); 1 for a year; the day of the year for a day */
    private function __construct(
        public Frequency $frequency,
        public int $year,
        public int $number,
    ) {
    }

    public static function year(int $year): self
    {
        return new self(Frequency::ANNUAL, self::checkYear($year), 1);
    }

    public static function semester(int $year, int $half): self
    {
        return new self(Frequency::SEMIANNUAL, self::checkYear($year), self::checkRange($half, 1, 2, 'half-year'));
    }

    public static function quarter(int $year, int $quarter): self
    {
        return new self(Frequency::QUARTERLY, self::checkYear($year), self::checkRange($quarter, 1, 4, 'quarter'));
    }

    public static function month(int $year, int $month): self
    {
        return new self(Frequency::MONTHLY, self::checkYear($year), self::checkRange($month, 1, 12, 'month'));
    }

    /** An ISO 8601 week: week 1 holds the year's first Thursday. */
    public static function week(int $year, int $week): self
    {
        $weeks = (int) (new \DateTimeImmutable(\sprintf('%04d-12-28', self::checkYear($year)), new \DateTimeZone('UTC')))->format('W');

        return new self(Frequency::WEEKLY, $year, self::checkRange($week, 1, $weeks, 'week'));
    }

    /** A day, in the calendar the date is written in (its time and time zone left out). */
    public static function day(\DateTimeInterface|string $date, Frequency $frequency = Frequency::DAILY): self
    {
        if (\is_string($date)) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
            if (false === $parsed || $parsed->format('Y-m-d') !== $date) {
                throw new OmnistateException(\sprintf('"%s" is not a day (YYYY-MM-DD).', $date));
            }
            $date = $parsed;
        }
        if (Frequency::DAILY !== $frequency && Frequency::BUSINESS !== $frequency) {
            throw new OmnistateException(\sprintf('A day is daily or business-daily, not %s.', $frequency->name));
        }

        return new self($frequency, self::checkYear((int) $date->format('Y')), (int) $date->format('z') + 1);
    }

    /**
     * A period as SDMX, ISO 8601 or a source writes it: "2026", "2026-S1",
     * "2026-Q3" (also "2026Q3", "2026-T3"), "2026-09" (also "2026M09"),
     * "2026-W05", "2026-11-09". $frequency, when the source gives one,
     * reads a day as business-daily.
     *
     * @throws OmnistateException not a period
     */
    public static function parse(string $period, ?Frequency $frequency = null): self
    {
        $period = strtoupper(trim($period));
        $found = match (true) {
            1 === preg_match('/^(\d{4})(?:-A1|-01-01\/P1Y)?$/', $period, $m) => self::year((int) $m[1]),
            1 === preg_match('/^(\d{4})-?S([12])$/', $period, $m) => self::semester((int) $m[1], (int) $m[2]),
            1 === preg_match('/^(\d{4})-?[QT]([1-4])$/', $period, $m) => self::quarter((int) $m[1], (int) $m[2]),
            1 === preg_match('/^(\d{4})(?:-|M)(\d{2})$/', $period, $m) => self::month((int) $m[1], (int) $m[2]),
            1 === preg_match('/^(\d{4})-?W(\d{2})$/', $period, $m) => self::week((int) $m[1], (int) $m[2]),
            1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $period) => self::day($period, Frequency::BUSINESS === $frequency ? Frequency::BUSINESS : Frequency::DAILY),
            default => throw new OmnistateException(\sprintf('"%s" is not a period (2026, 2026-S1, 2026-Q3, 2026-09, 2026-W05, 2026-11-09).', $period)),
        };

        return $found;
    }

    /** The quarter a date falls in. */
    public static function quarterOf(\DateTimeInterface $date): self
    {
        return self::quarter((int) $date->format('Y'), intdiv((int) $date->format('n') - 1, 3) + 1);
    }

    /** Its first day, at midnight UTC. */
    public function start(): \DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');

        return match ($this->frequency) {
            Frequency::ANNUAL => new \DateTimeImmutable(\sprintf('%04d-01-01', $this->year), $utc),
            Frequency::SEMIANNUAL => new \DateTimeImmutable(\sprintf('%04d-%02d-01', $this->year, 6 * ($this->number - 1) + 1), $utc),
            Frequency::QUARTERLY => new \DateTimeImmutable(\sprintf('%04d-%02d-01', $this->year, 3 * ($this->number - 1) + 1), $utc),
            Frequency::MONTHLY => new \DateTimeImmutable(\sprintf('%04d-%02d-01', $this->year, $this->number), $utc),
            Frequency::WEEKLY => (new \DateTimeImmutable('now', $utc))->setISODate($this->year, $this->number)->setTime(0, 0),
            Frequency::DAILY, Frequency::BUSINESS => (new \DateTimeImmutable(\sprintf('%04d-01-01', $this->year), $utc))->modify(\sprintf('+%d days', $this->number - 1)),
        };
    }

    /** Its last day, at midnight UTC. */
    public function end(): \DateTimeImmutable
    {
        return $this->next()->start()->modify('-1 day');
    }

    public function contains(\DateTimeInterface $date): bool
    {
        $day = (new \DateTimeImmutable($date->format('Y-m-d'), new \DateTimeZone('UTC')));

        return $day >= $this->start() && $day <= $this->end();
    }

    /**
     * The period $periods later (earlier when negative), in its own unit: a
     * quarter's shift(-4) is the same quarter a year before.
     */
    public function shift(int $periods): self
    {
        if (0 === $periods) {
            return $this;
        }
        $perYear = $this->frequency->perYear();
        if (null !== $perYear) {
            $index = $this->year * $perYear + $this->number - 1 + $periods;

            return new self($this->frequency, self::checkYear(intdiv($index, $perYear)), $index % $perYear + 1);
        }
        if (Frequency::WEEKLY === $this->frequency) {
            $start = $this->start()->modify(\sprintf('%+d weeks', $periods));

            return self::week((int) $start->format('o'), (int) $start->format('W'));
        }

        return self::day($this->start()->modify(\sprintf('%+d days', $periods)), $this->frequency);
    }

    public function next(): self
    {
        return $this->shift(1);
    }

    public function previous(): self
    {
        return $this->shift(-1);
    }

    /** -1, 0 or 1: by start, then by end (a year before its first quarter). */
    public function compare(self $other): int
    {
        return [$this->start(), $this->end()] <=> [$other->start(), $other->end()];
    }

    public function equals(self $other): bool
    {
        return $this->frequency === $other->frequency && $this->year === $other->year && $this->number === $other->number;
    }

    public function __toString(): string
    {
        return match ($this->frequency) {
            Frequency::ANNUAL => \sprintf('%04d', $this->year),
            Frequency::SEMIANNUAL => \sprintf('%04d-S%d', $this->year, $this->number),
            Frequency::QUARTERLY => \sprintf('%04d-Q%d', $this->year, $this->number),
            Frequency::MONTHLY => \sprintf('%04d-%02d', $this->year, $this->number),
            Frequency::WEEKLY => \sprintf('%04d-W%02d', $this->year, $this->number),
            Frequency::DAILY, Frequency::BUSINESS => $this->start()->format('Y-m-d'),
        };
    }

    private static function checkYear(int $year): int
    {
        return $year >= 1 && $year <= 9999 ? $year : throw new OmnistateException(\sprintf('%d is not a year a period is written for.', $year));
    }

    private static function checkRange(int $value, int $min, int $max, string $what): int
    {
        return $value >= $min && $value <= $max ? $value : throw new OmnistateException(\sprintf('%d is not a %s (%d to %d).', $value, $what, $min, $max));
    }
}
