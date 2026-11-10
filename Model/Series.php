<?php

namespace Omnistate\Model;

/**
 * A statistical series as its source publishes it: what it measures, in
 * what unit, how often, since when it was updated, the credit its reuse
 * asks for - and the observations asked, oldest first. With history, every
 * vintage of every value, each marked with the date it was published from
 * (Observation::$vintage); observation() and latest() then read the current
 * one.
 */
final readonly class Series
{
    /**
     * @param array<string, string> $titles       its title by language (fr, en), when the source gives several
     * @param array<string, string> $dimensions   what identifies it within its dataset, by SDMX dimension (FREQ, REF_AREA, CURRENCY...)
     * @param list<Observation>     $observations oldest first
     */
    public function __construct(
        /** Where it came from: "insee", "eurostat", "ecb" - its registry's name(). */
        public string $source,
        /** Its identifier there, as series() takes it: an INSEE idBank, "EXR/D.USD.EUR.SP00.A"... */
        public string $id,
        public string $title,
        public ?Frequency $frequency = null,
        /** What a value counts: "Percent per annum", "US dollar", "Index"; null when the source does not say. */
        public ?string $unit = null,
        /** The power of ten values are multiplied by (3: thousands); 0 when they are as written. */
        public int $unitMultiplier = 0,
        /** An index's base period, when the source says it apart from the title ("2015=100", "99Q1=100"). */
        public ?string $base = null,
        /** The dataset (dataflow) it belongs to, when the source says. */
        public ?string $dataset = null,
        /** When the source last updated it. */
        public ?\DateTimeImmutable $updated = null,
        /** The credit its reuse asks for, as the source words it: "Insee, BDM, série 001515333, extraction au 10/11/2026". */
        public string $credit = '',
        public ?string $licence = null,
        /** Its page at the source. */
        public ?string $url = null,
        public array $titles = [],
        public array $dimensions = [],
        public array $observations = [],
        /** Whether the observations hold every vintage (asked with history). */
        public bool $history = false,
    ) {
    }

    /** "insee:001515333": what Omnistate::series() takes to find it again. */
    public function ref(): string
    {
        return $this->source.':'.$this->id;
    }

    /** The value of that period, its current vintage; null when the series has none for it (not published yet, or not asked). */
    public function observation(TimePeriod|string $period): ?Observation
    {
        $period = \is_string($period) ? TimePeriod::parse($period, $this->frequency) : $period;
        $found = null;
        foreach ($this->observations as $observation) {
            if ($observation->period->equals($period) && (null === $found || self::newer($observation, $found))) {
                $found = $observation;
            }
        }

        return $found;
    }

    /** The latest period's value, its current vintage; null when no observation was asked. */
    public function latest(): ?Observation
    {
        $latest = null;
        foreach ($this->observations as $observation) {
            if (null === $latest || $observation->period->compare($latest->period) > 0 || ($observation->period->equals($latest->period) && self::newer($observation, $latest))) {
                $latest = $observation;
            }
        }

        return $latest;
    }

    /**
     * Every vintage of a period's value, the first published first: its
     * revisions. Without history, the current one alone.
     *
     * @return list<Observation>
     */
    public function vintages(TimePeriod|string $period): array
    {
        $period = \is_string($period) ? TimePeriod::parse($period, $this->frequency) : $period;
        $vintages = array_values(array_filter($this->observations, static fn (Observation $observation): bool => $observation->period->equals($period)));
        usort($vintages, static fn (Observation $a, Observation $b): int => self::newer($a, $b) ? 1 : (self::newer($b, $a) ? -1 : 0));

        return $vintages;
    }

    /** The current vintage (null) is newer than any dated one; dated ones by date. */
    private static function newer(Observation $a, Observation $b): bool
    {
        if (null === $b->vintage) {
            return false;
        }

        return null === $a->vintage || $a->vintage > $b->vintage;
    }
}
