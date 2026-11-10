<?php

namespace Omnistate\Policy;

use Omnistate\Model\Series;
use Omnistate\Model\TimePeriod;
use Omnistate\Registry\StatisticsRegistryInterface;

/**
 * A statistics source as Omnistate::statisticsRegistries() and
 * statisticsRegistry() give it: series() and datasets() under its usage
 * policy's guard. inner() is the source itself.
 */
final class GuardedStatisticsRegistry implements StatisticsRegistryInterface
{
    public function __construct(private readonly StatisticsRegistryInterface $registry, private readonly Guards $guards)
    {
    }

    public function inner(): StatisticsRegistryInterface
    {
        return $this->registry;
    }

    public function name(): string
    {
        return $this->registry->name();
    }

    public function supports(string $series): bool
    {
        return $this->registry->supports($series);
    }

    public function series(string $series, ?TimePeriod $from = null, ?TimePeriod $to = null, ?int $last = null, bool $history = false): ?Series
    {
        return $this->guards->call($this->registry->name(), $this->registry, 'series', fn (): ?Series => $this->registry->series($series, $from, $to, $last, $history));
    }

    public function datasets(string $query, int $limit = 10): array
    {
        return $this->guards->call($this->registry->name(), $this->registry, 'datasets', fn (): array => $this->registry->datasets($query, $limit));
    }
}
