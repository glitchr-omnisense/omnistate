<?php

namespace Omnistate\Model;

/**
 * A date as a register knows it: often whole, sometimes a year and a month,
 * sometimes a year alone ("born in 1932"). What is not known is null - never
 * a 1st of January made up.
 */
final readonly class PartialDate implements \Stringable
{
    public function __construct(
        public ?int $year = null,
        /** 1 to 12 */
        public ?int $month = null,
        public ?int $day = null,
    ) {
    }

    /**
     * "1932-11-29", "19321129", "29/11/1932", "1932-11", "1932", and the
     * registers' unknown parts written as zeros ("19320000"); null when no
     * year can be read.
     */
    public static function parse(?string $value): ?self
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)
            || preg_match('/^(\d{4})(?:-(\d{1,2}))?(?:-(\d{1,2}))?$/', $value, $m)) {
            return self::of((int) $m[1], (int) ($m[2] ?? 0), (int) ($m[3] ?? 0));
        }
        if (preg_match('#^(\d{1,2})[/.](\d{1,2})[/.](\d{4})$#', $value, $m)) {
            return self::of((int) $m[3], (int) $m[2], (int) $m[1]);
        }
        if (preg_match('#^(\d{1,2})[/.](\d{4})$#', $value, $m)) {
            return self::of((int) $m[2], (int) $m[1], 0);
        }

        return null;
    }

    /** Zeros and out-of-range parts are unknown parts; no year, no date. */
    public static function of(?int $year, ?int $month = null, ?int $day = null): ?self
    {
        if (!$year) {
            return null;
        }
        $month = $month >= 1 && $month <= 12 ? $month : null;
        $day = null !== $month && $day >= 1 && $day <= 31 ? $day : null;

        return new self($year, $month, $day);
    }

    public static function fromDate(\DateTimeInterface $date): self
    {
        return new self((int) $date->format('Y'), (int) $date->format('n'), (int) $date->format('j'));
    }

    public function isComplete(): bool
    {
        return null !== $this->year && null !== $this->month && null !== $this->day;
    }

    /** The day itself when it is whole, null otherwise. */
    public function toDate(): ?\DateTimeImmutable
    {
        return $this->isComplete() && checkdate($this->month, $this->day, $this->year)
            ? (new \DateTimeImmutable('today'))->setDate($this->year, $this->month, $this->day)
            : null;
    }

    /** The first day it can be. */
    public function earliest(): ?\DateTimeImmutable
    {
        return null === $this->year ? null : (new \DateTimeImmutable('today'))->setDate($this->year, $this->month ?? 1, $this->day ?? 1);
    }

    /** The last day it can be. */
    public function latest(): ?\DateTimeImmutable
    {
        if (null === $this->year) {
            return null;
        }
        $month = $this->month ?? 12;
        $day = $this->day ?? (int) (new \DateTimeImmutable('today'))->setDate($this->year, $month, 1)->format('t');

        return (new \DateTimeImmutable('today'))->setDate($this->year, $month, $day);
    }

    /** ISO 8601, as far as it is known: "1932-11-29", "1932-11", "1932". */
    public function __toString(): string
    {
        if (null === $this->year) {
            return '';
        }

        return sprintf('%04d', $this->year)
            .(null !== $this->month ? sprintf('-%02d', $this->month) : '')
            .(null !== $this->month && null !== $this->day ? sprintf('-%02d', $this->day) : '');
    }
}
