# Civil registers: acts and documents about people

What public registers index about a person - births, baptisms, marriages, deaths, burials, and
the other documents an archive holds - through one contract, `CivilRegistryInterface`.

```php
use Omnistate\Model\{CivilQuery, CivilRecordKind, Period};

$records = $omnistate->civilRecords(new CivilQuery(
    familyName: 'Chirac', givenName: 'Jacques',
    born: Period::year(1932),              // or Period::around(1932), Period::years(1930, 1935)
    country: 'FR',                         // ISO 3166-1 alpha-2; null: every register
    kinds: [CivilRecordKind::DEATH],       // empty: any kind
));

$record = $records[0];
$record->kind;                    // CivilRecordKind::DEATH
$record->date;                    // PartialDate "2019-09-26"
$record->place;                   // Place: name, code (INSEE), postcode, region, country, coordinates
$record->principal()->name();     // "Jacques Rene Chirac" - $record->persons names everyone in the act
$record->archive;                 // who keeps the original, its reference
$record->images;                  // scans of the act, when the register publishes them
$record->url;                     // its page at the register

$omnistate->civilRecord($record->source, $record->identifier);   // the whole record again: every person, the scans
```

## The registers

| Package | Countries | Kinds of records | Picture of the act | Access |
|---|---|---|---|---|
| `omnistate/matchid` | FR | deaths, since 1970 (INSEE's file) | no | free, no key (a token raises the quota) |
| `omnistate/openarchieven` | NL, BE, FR, SR | births, baptisms, marriages, deaths, burials, others (population registers, notarial deeds...) | yes, when the archive scanned it | free, no key, 4 calls a second |
| `omnistate/national-archives-uk` | GB | others only: a catalogue of archives (wills, service records, parish papers), **not** civil registration | no | free, no key |

Each register says what it covers, so a search screen can tell before asking:

```php
foreach ($omnistate->civilRegistries() as $registry) {     // or civilRegistries('NL', CivilRecordKind::BIRTH)
    $registry->name();        // "openarchieven"
    $registry->countries();   // ['NL', 'BE', 'FR', 'SR']
    $registry->kinds();       // [CivilRecordKind::BIRTH, ...]
    $registry->period();      // Period "1970/…" or null
    $registry->hasImages();   // true
    $registry->supports($query);
}
$omnistate->civilCountries();  // ['BE', 'FR', 'GB', 'NL', 'SR']
```

## A register that is down

`UnavailableException`, never an empty list: "no answer" is not "no record". `civilRecords($query)`
asks every register the question is one for; one that is down throws, and takes the others'
answers with it. A screen that must keep what the others found asks them one by one:

```php
foreach ($omnistate->civilRegistries() as $registry) {
    if (!$registry->supports($query)) { continue; }
    try {
        $found[$registry->name()] = $omnistate->civilRecords($query, $registry->name());
    } catch (UnavailableException $e) {
        $down[$registry->name()] = $e->retryAfter;
    }
}
```

`NotSupportedException`: no register installed takes the question (its country, its kinds, no name).

## The models

- `CivilQuery`: `familyName`, `givenName`, `born`, `died`, `dated` (the event's date, whatever it is),
  `place`, `country`, `kinds`, `sex`, `limit` (1 to 100), `page`. A register uses what it can search on.
- `CivilRecord`: `identifier`, `kind`, `source`, `date`, `place`, `persons`, `archive`, `url`, `images`,
  `title`, `number` (the act's number), `description`, `period` (a document dated by a span), `raw`.
- `CivilPerson`: `familyName`, `givenNames`, `sex`, `role` (as the register writes it), `birthDate`,
  `birthPlace`, `deathDate`, `deathPlace`, `age`, `profession`, `fullName`.
- `PartialDate`: a year, maybe a month, maybe a day - what is not known is null, never a made-up
  1st of January. `PartialDate::parse()` reads `19321129`, `1932-11`, `29/11/1932`, `19320000`.
- `Period`: `year()`, `years()`, `since()`, `around()`, `day()`, `contains()`.
- `Place`, `Archive`, the enums `CivilRecordKind` (with `gedcom()`: BIRT, CHR, MARR, DEAT, BURI) and `Sex`.
- `Identifier\CountryCode`: `alpha2('FRA')` is `FR`. No network.

## Symfony

`OmnistateBundle` registers the civil register packages installed and tags them
`omnistate.civil_registry`; a class of yours implementing `CivilRegistryInterface` is tagged by
autoconfiguration and asked too.

```yaml
omnistate:
    matchid:
        token: '%env(MATCHID_TOKEN)%'   # optional
```

## What is not there

- England and Wales' births, marriages and deaths: the General Register Office has no public API.
- The United States' National Archives catalogue (NARA): its API needs a key, and it is a catalogue
  of record descriptions, not an index of people; no package until a key is at hand to verify one.
- Japan: the koseki are not public.
- Collaborative trees (FamilySearch, Geneanet) are not registers.
