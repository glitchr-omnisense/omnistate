<?php

namespace Omnistate\Registry;

use Omnistate\Model\Dataset;
use Omnistate\Model\Series;
use Omnistate\Model\TimePeriod;

/**
 * A source of public statistics: a statistical office's series (INSEE's
 * BDM), a union's (Eurostat), a central bank's (the ECB's). All three speak
 * SDMX or a JSON form of it: a series belongs to a dataset (a dataflow) and
 * is identified there by a key; each source says how its ids are written
 * (supports()).
 */
interface StatisticsRegistryInterface
{
    /** A short name, the Series' $source: "insee". */
    public function name(): string;

    /** Whether the id is one it reads (an INSEE idBank, "EXR/D.USD.EUR.SP00.A", "prc_hicp_manr?geo=FR..."). */
    public function supports(string $series): bool;

    /**
     * A series: its metadata, and its observations from $from to $to (both
     * included, either open), or its $last ones, oldest first. With
     * $history, every vintage of every value (its revisions), where the
     * source keeps them. Null when the source does not know it.
     *
     * @throws \Omnistate\Exception\UnavailableException the source did not answer
     * @throws \Omnistate\Exception\NotSupportedException it keeps no history, and $history was asked
     */
    public function series(string $series, ?TimePeriod $from = null, ?TimePeriod $to = null, ?int $last = null, bool $history = false): ?Series;

    /**
     * The datasets whose title or id holds every word of the query, the
     * best first: where to look for a series.
     *
     * @return list<Dataset>
     *
     * @throws \Omnistate\Exception\UnavailableException
     */
    public function datasets(string $query, int $limit = 10): array;
}
