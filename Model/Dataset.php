<?php

namespace Omnistate\Model;

/**
 * A group of statistical series a source publishes together: an SDMX
 * dataflow (INSEE's IRL, the ECB's EXR), a Eurostat dataset (prc_hicp_manr).
 * Its series are asked by a key within it (each source's id syntax).
 */
final readonly class Dataset
{
    public function __construct(
        /** Where it came from: "insee", "eurostat", "ecb". */
        public string $source,
        public string $id,
        public string $title,
        /** When its data was last updated, when the source says. */
        public ?\DateTimeImmutable $updated = null,
        /** Its page at the source: documentation, the series it holds. */
        public ?string $url = null,
        /** How many series it holds, when the source says. */
        public ?int $series = null,
    ) {
    }
}
