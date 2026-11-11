<?php

namespace Omnistate\Tests;

use Omnistate\Exception\UnavailableException;
use Omnistate\Model\Archive;
use Omnistate\Model\CivilPerson;
use Omnistate\Model\CivilQuery;
use Omnistate\Model\CivilRecord;
use Omnistate\Model\CivilRecordKind;
use Omnistate\Model\PartialDate;
use Omnistate\Model\Period;
use Omnistate\Model\Place;
use Omnistate\Model\Sex;
use Omnistate\Registry\CivilRegistryInterface;

/** A civil register of one record (D-1), counting its searches; $down makes it unavailable. */
final class FakeCivilRegistry implements CivilRegistryInterface
{
    public int $searches = 0;
    public bool $down = false;

    /**
     * @param list<string>          $countries
     * @param list<CivilRecordKind> $kinds
     */
    public function __construct(private string $name, private array $countries, private array $kinds)
    {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function countries(): array
    {
        return $this->countries;
    }

    public function kinds(): array
    {
        return $this->kinds;
    }

    public function period(): ?Period
    {
        return null;
    }

    public function hasImages(): bool
    {
        return false;
    }

    public function supports(CivilQuery $query): bool
    {
        return $query->hasName() && $query->inCountries(...$this->countries) && $query->wantsAny(...$this->kinds);
    }

    public function search(CivilQuery $query): array
    {
        ++$this->searches;
        if ($this->down) {
            throw new UnavailableException('down', 30);
        }

        $record = $this->find('D-1');

        return null === $record ? [] : [$record];
    }

    public function find(string $identifier): ?CivilRecord
    {
        return 'D-1' !== $identifier ? null : new CivilRecord(
            'D-1', CivilRecordKind::DEATH, $this->name, new PartialDate(1969, 4, 2), new Place('Illkirch', country: 'FR'),
            [new CivilPerson('Vasseur', ['Henri'], Sex::MALE, 'Deceased')], new Archive('Archives fictives', reference: 'registre 12'),
        );
    }
}
