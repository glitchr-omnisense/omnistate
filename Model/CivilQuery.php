<?php

namespace Omnistate\Model;

/**
 * What is asked of a civil register: someone's name, and whatever narrows it
 * down. A register uses what it can search on and ignores the rest - but it
 * says beforehand, with supports(), whether the question is one for it (its
 * country, its kinds of records).
 *
 *     new CivilQuery(familyName: 'Chirac', givenName: 'Jacques', born: Period::year(1932), country: 'FR')
 */
final readonly class CivilQuery
{
    /** @var list<CivilRecordKind> */
    public array $kinds;
    public ?string $country;
    public int $limit;
    public int $page;

    /**
     * @param list<CivilRecordKind> $kinds   the kinds wanted; empty: any
     * @param string|null           $country ISO 3166-1 alpha-2 of the country whose registers are asked; null: any
     */
    public function __construct(
        public ?string $familyName = null,
        public ?string $givenName = null,
        public ?Period $born = null,
        public ?Period $died = null,
        /** When the event recorded took place, whatever it is. */
        public ?Period $dated = null,
        /** The town of the event. */
        public ?string $place = null,
        ?string $country = null,
        array $kinds = [],
        public ?Sex $sex = null,
        int $limit = 20,
        int $page = 1,
    ) {
        $this->kinds = array_values($kinds);
        $this->country = null === $country || '' === trim($country) ? null : strtoupper(trim($country));
        $this->limit = max(1, min(100, $limit));
        $this->page = max(1, $page);
    }

    /** "Jacques Chirac" */
    public function name(): string
    {
        return trim(($this->givenName ?? '').' '.($this->familyName ?? ''));
    }

    public function hasName(): bool
    {
        return '' !== $this->name();
    }

    public function wants(CivilRecordKind $kind): bool
    {
        return [] === $this->kinds || \in_array($kind, $this->kinds, true);
    }

    /** Whether any of these kinds is wanted. */
    public function wantsAny(CivilRecordKind ...$kinds): bool
    {
        foreach ($kinds as $kind) {
            if ($this->wants($kind)) {
                return true;
            }
        }

        return false;
    }

    /** Whether a register of these countries may be asked. */
    public function inCountries(string ...$countries): bool
    {
        return null === $this->country || \in_array($this->country, $countries, true);
    }

    /** A key for a cache: the same question, the same key. */
    public function key(): string
    {
        return implode('|', [
            mb_strtolower((string) $this->familyName), mb_strtolower((string) $this->givenName),
            (string) $this->born, (string) $this->died, (string) $this->dated,
            mb_strtolower((string) $this->place), (string) $this->country,
            implode(',', array_map(static fn (CivilRecordKind $kind) => $kind->value, $this->kinds)),
            $this->sex?->value, $this->limit, $this->page,
        ]);
    }
}
