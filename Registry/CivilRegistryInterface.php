<?php

namespace Omnistate\Registry;

use Omnistate\Model\CivilQuery;
use Omnistate\Model\CivilRecord;
use Omnistate\Model\CivilRecordKind;
use Omnistate\Model\Period;

/**
 * A public register of acts and documents about people: a country's deaths
 * file (France: INSEE's, through matchID), the indexes of its archives (the
 * Netherlands and Belgium: Open Archives), an archive's catalogue. Each one
 * says what it covers - which countries, which kinds of records, which years
 * - so that a search screen can tell before asking.
 */
interface CivilRegistryInterface
{
    /** A short name, the CivilRecord's $source: "matchid". */
    public function name(): string;

    /** @return list<string> ISO 3166-1 alpha-2 codes of the countries whose records it holds */
    public function countries(): array;

    /** @return list<CivilRecordKind> the kinds of records it holds */
    public function kinds(): array;

    /** The years it covers, when it has bounds (France's deaths file: since 1970); null: no bound to tell. */
    public function period(): ?Period;

    /** Whether it gives the pictures of the acts (CivilRecord::$images), for some records at least. */
    public function hasImages(): bool;

    /** Whether the question is one for it: its country, one of its kinds, something it can search on. */
    public function supports(CivilQuery $query): bool;

    /**
     * The records that match, the likeliest first; none is an empty list.
     *
     * @return list<CivilRecord>
     *
     * @throws \Omnistate\Exception\UnavailableException the register did not answer
     */
    public function search(CivilQuery $query): array;

    /**
     * A record by the identifier a search gave (CivilRecord::$identifier),
     * with everything the register tells about it; null when it does not know it.
     *
     * @throws \Omnistate\Exception\UnavailableException
     */
    public function find(string $identifier): ?CivilRecord;
}
