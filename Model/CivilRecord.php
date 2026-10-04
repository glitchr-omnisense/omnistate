<?php

namespace Omnistate\Model;

/**
 * An act, or a document about a person, as a public register indexes it:
 * what it is, when, where, who is named in it, who keeps the original and -
 * when the register gives them - the pictures of it. $raw keeps the
 * register's whole answer.
 */
final readonly class CivilRecord
{
    public function __construct(
        /** What Omnistate::civilRecord() takes to find it again in its register. */
        public string $identifier,
        public CivilRecordKind $kind,
        /** The register it came from: "matchid", "openarchieven"... */
        public string $source,
        /** Of the event (the birth, the death), not of the paper. */
        public ?PartialDate $date = null,
        public ?Place $place = null,
        /** @var list<CivilPerson> the one the record is about first */
        public array $persons = [],
        public ?Archive $archive = null,
        /** The record's page at the register. */
        public ?string $url = null,
        /** @var list<string> scans of the act, when the register publishes them */
        public array $images = [],
        /** What the register calls it: "Civil registration births", a catalogue title. */
        public ?string $title = null,
        /** The act's number in its register. */
        public ?string $number = null,
        public ?string $description = null,
        /** When the register dates it by a span ("1939-1945") rather than a day. */
        public ?Period $period = null,
        public array $raw = [],
    ) {
    }

    /** The one the record is about. */
    public function principal(): ?CivilPerson
    {
        return $this->persons[0] ?? null;
    }

    public function hasImages(): bool
    {
        return [] !== $this->images;
    }
}
