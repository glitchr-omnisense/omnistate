<?php

namespace Omnistate\Tests\Statistics;

use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\RateLimit;
use Omnisense\Contracts\Policy\UsagePolicy;
use Omnistate\Model\Dataset;
use Omnistate\Model\Frequency;
use Omnistate\Model\Observation;
use Omnistate\Model\Series;
use Omnistate\Model\TimePeriod;
use Omnistate\Registry\StatisticsRegistryInterface;

/** A source of one quarterly index, "IDX", 100 in 2025-Q1 and 1 % a quarter; 10 calls a minute. */
final class FakeStatistics implements StatisticsRegistryInterface, DeclaresUsagePolicyInterface
{
    /** @var list<array{string, ?string, ?string, ?int}> */
    public array $asked = [];

    public function __construct(private readonly string $name = 'fake')
    {
    }

    public function usagePolicy(): UsagePolicy
    {
        return UsagePolicy::builder($this->name)->limit(RateLimit::perMinute(10))->attribution('Source: Fake')->build();
    }

    public function name(): string
    {
        return $this->name;
    }

    public function supports(string $series): bool
    {
        return 'IDX' === $series;
    }

    public function series(string $series, ?TimePeriod $from = null, ?TimePeriod $to = null, ?int $last = null, bool $history = false): Series
    {
        $this->asked[] = [$series, null === $from ? null : (string) $from, null === $to ? null : (string) $to, $last];
        $observations = [];
        for ($period = TimePeriod::quarter(2025, 1), $value = 100.0; $period->compare(TimePeriod::quarter(2026, 2)) <= 0; $period = $period->next(), $value *= 1.01) {
            if ((null === $from || $period->compare($from) >= 0) && (null === $to || $period->compare($to) <= 0)) {
                $observations[] = new Observation($period, round($value, 2));
            }
        }

        return new Series($this->name, 'IDX', 'An index', Frequency::QUARTERLY, credit: 'Fake, IDX', observations: null === $last ? $observations : \array_slice($observations, -$last));
    }

    public function datasets(string $query, int $limit = 10): array
    {
        return [new Dataset($this->name, 'INDICES', 'Indices')];
    }
}
