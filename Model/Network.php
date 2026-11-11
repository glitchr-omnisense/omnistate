<?php

namespace Omnistate\Model;

/** An IP range or an autonomous system, as its regional registry's RDAP server describes it. */
final readonly class Network
{
    public function __construct(
        public string $handle,
        public string $source,
        public ?string $name = null,
        /** "ALLOCATED PA", "ASSIGNED PA", "DIRECT ALLOCATION"... */
        public ?string $type = null,
        public ?string $country = null,
        /** An IP range: its first and last address. */
        public ?string $startAddress = null,
        public ?string $endAddress = null,
        /** An autonomous system: its numbers. */
        public ?int $startAutnum = null,
        public ?int $endAutnum = null,
        public ?Contact $registrant = null,
        public ?Contact $abuse = null,
        public ?\DateTimeImmutable $registeredAt = null,
        public ?\DateTimeImmutable $changedAt = null,
        public ?string $server = null,
        /** @var array<mixed> the register's whole answer, as it was decoded */
        public array $raw = [],
    ) {
    }
}
