<?php

namespace Omnistate\Model;

use Omnistate\Exception\OmnistateException;

/**
 * How an index moved between two of its periods: the IRL of a lease's
 * reference quarter a year apart, the ILC or the ILAT of a commercial
 * lease's, a price index between two months. Arithmetic on two published
 * values, nothing more: which periods a contract refers to, the dates it
 * applies on, the caps the law sets and the rounding of an amount are the
 * application's.
 *
 *     $change = $omnistate->indexChange('insee:IRL', TimePeriod::quarter(2025, 2), TimePeriod::quarter(2026, 2));
 *     $change->ratio();          // 148.37 / 146.68
 *     $change->apply(850.00);    // the amount times the ratio, unrounded
 */
final readonly class IndexChange
{
    public function __construct(
        /** The series' ref ("insee:001515333"). */
        public string $series,
        public Observation $from,
        public Observation $to,
        /** The credit the series' reuse asks for. */
        public string $credit = '',
    ) {
        if (null === $from->value || null === $to->value || 0.0 === $from->value) {
            throw new OmnistateException(\sprintf('%s has no value to compare for %s.', $series, null === $from->value || 0.0 === $from->value ? $from->period : $to->period));
        }
    }

    /**
     * The change between two periods of a series given with both.
     *
     * @throws OmnistateException the series holds no value for one of them (not published yet, or not asked)
     */
    public static function between(Series $series, TimePeriod $from, TimePeriod $to): self
    {
        $missing = static fn (TimePeriod $period) => new OmnistateException(\sprintf('%s has no value for %s yet.', $series->ref(), $period));

        return new self($series->ref(), $series->observation($from) ?? throw $missing($from), $series->observation($to) ?? throw $missing($to), $series->credit);
    }

    /** The later value over the earlier one, as published (1.0115...). */
    public function ratio(): float
    {
        return (float) $this->to->value / (float) $this->from->value;
    }

    /** The change in percent (1.15 for +1.15 %). */
    public function percent(): float
    {
        return ($this->ratio() - 1) * 100;
    }

    /** An amount moved as the index moved: $amount times the ratio, unrounded. */
    public function apply(float $amount): float
    {
        return $amount * (float) $this->to->value / (float) $this->from->value;
    }

    /** Whether either value may still change (provisional, estimated...). */
    public function isProvisional(): bool
    {
        return $this->from->isProvisional() || $this->to->isProvisional();
    }
}
