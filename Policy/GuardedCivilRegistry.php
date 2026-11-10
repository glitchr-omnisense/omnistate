<?php

namespace Omnistate\Policy;

use Omnistate\Model\CivilQuery;
use Omnistate\Model\CivilRecord;
use Omnistate\Model\Period;
use Omnistate\Registry\CivilRegistryInterface;

/**
 * A civil register as Omnistate::civilRegistries() and civilRegistry() give
 * it: search() and find() under its usage policy's guard, what it says of
 * its coverage as it says it. inner() is the register itself.
 */
final class GuardedCivilRegistry implements CivilRegistryInterface
{
    public function __construct(private readonly CivilRegistryInterface $registry, private readonly Guards $guards)
    {
    }

    public function inner(): CivilRegistryInterface
    {
        return $this->registry;
    }

    public function name(): string
    {
        return $this->registry->name();
    }

    public function countries(): array
    {
        return $this->registry->countries();
    }

    public function kinds(): array
    {
        return $this->registry->kinds();
    }

    public function period(): ?Period
    {
        return $this->registry->period();
    }

    public function hasImages(): bool
    {
        return $this->registry->hasImages();
    }

    public function supports(CivilQuery $query): bool
    {
        return $this->registry->supports($query);
    }

    public function search(CivilQuery $query): array
    {
        return $this->guards->call($this->registry->name(), $this->registry, 'search', fn (): array => $this->registry->search($query));
    }

    public function find(string $identifier): ?CivilRecord
    {
        return $this->guards->call($this->registry->name(), $this->registry, 'find', fn (): ?CivilRecord => $this->registry->find($identifier));
    }
}
