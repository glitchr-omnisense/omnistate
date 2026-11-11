<?php

namespace Omnistate\Model;

/** A domain name as its registry's RDAP server describes it. */
final readonly class Domain
{
    public function __construct(
        public string $name,
        public string $source,
        public ?string $handle = null,
        /** @var list<string> "active", "client transfer prohibited"... */
        public array $statuses = [],
        public ?Contact $registrar = null,
        /** Null when the registry does not publish it (most do not, since the GDPR). */
        public ?Contact $registrant = null,
        public ?\DateTimeImmutable $registeredAt = null,
        public ?\DateTimeImmutable $expiresAt = null,
        public ?\DateTimeImmutable $changedAt = null,
        /** @var list<string> */
        public array $nameservers = [],
        public bool $dnssec = false,
        /** The server that answered. */
        public ?string $server = null,
        /** @var array<mixed> the register's whole answer, as it was decoded */
        public array $raw = [],
    ) {
    }
}
