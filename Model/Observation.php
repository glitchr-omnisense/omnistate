<?php

namespace Omnistate\Model;

/**
 * One value of a statistical series: the period it stands for, the value
 * (null when the source has none: missing, confidential), what the source
 * says of it.
 */
final readonly class Observation
{
    /**
     * @param list<string> $flags the source's own codes for it, as given (INSEE's OBS_STATUS and OBS_QUAL, Eurostat's status, the ECB's OBS_STATUS)
     */
    public function __construct(
        public TimePeriod $period,
        public ?float $value,
        public ObservationStatus $status = ObservationStatus::NORMAL,
        /** The value was revised since it was first published (INSEE's OBS_REV, a revised status). */
        public bool $revised = false,
        /** When the source published it, when it says (INSEE: its date in the Journal officiel). */
        public ?\DateTimeImmutable $published = null,
        /** With history: from when this value was the one published (the vintage it belongs to); null for the current one. */
        public ?\DateTimeImmutable $vintage = null,
        public array $flags = [],
    ) {
    }

    /** Whether the value may still change: provisional, semi-final, estimated or forecast. */
    public function isProvisional(): bool
    {
        return $this->status->isProvisional();
    }
}
