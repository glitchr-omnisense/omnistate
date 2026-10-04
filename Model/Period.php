<?php

namespace Omnistate\Model;

/** Between two dates, either of them open: "from 1970", "1850 to 1860", "in 1853". */
final readonly class Period implements \Stringable
{
    public function __construct(
        public ?PartialDate $from = null,
        public ?PartialDate $to = null,
    ) {
    }

    public static function year(int $year): self
    {
        return new self(new PartialDate($year), new PartialDate($year));
    }

    public static function years(?int $from, ?int $to): self
    {
        return new self(null === $from ? null : new PartialDate($from), null === $to ? null : new PartialDate($to));
    }

    public static function since(int $year): self
    {
        return new self(new PartialDate($year));
    }

    /** A year give or take a few: the way a genealogist searches a birth. */
    public static function around(int $year, int $margin = 2): self
    {
        return self::years($year - $margin, $year + $margin);
    }

    public static function day(PartialDate $date): self
    {
        return new self($date, $date);
    }

    /** One date, whole, at both ends. */
    public function isDay(): bool
    {
        return null !== $this->from && $this->from->isComplete() && (string) $this->from === (string) $this->to;
    }

    public function isYear(): bool
    {
        return null !== $this->from?->year && $this->from->year === $this->to?->year && !$this->isDay();
    }

    public function contains(PartialDate $date): bool
    {
        return (null === $this->from || $date->latest() >= $this->from->earliest())
            && (null === $this->to || $date->earliest() <= $this->to->latest());
    }

    public function __toString(): string
    {
        if ($this->isDay() || ($this->from && (string) $this->from === (string) $this->to)) {
            return (string) $this->from;
        }

        return ($this->from ?? '…').'/'.($this->to ?? '…');
    }
}
