---
title: Civil registers
order: 35
---

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
| `omnistate/nara` | US | others only: the National Archives Catalog (pension files, military service records, draft cards, naturalization indexes), **not** civil registration | yes, links to the scans at NARA | free, API key, 10,000 calls a month |

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
$omnistate->civilRegistry('matchid');   // one register by its name, null when not installed
```

`civilRegistries($country, $kind)` keeps the registers that declare that country and that kind;
it asks no one.

## What each register searches on

A register uses the fields of the `CivilQuery` it can search on and ignores the rest; its
`supports()` says beforehand whether the question is one for it.

| Field | matchid | openarchieven | national-archives-uk | nara |
|---|---|---|---|---|
| `familyName`, `givenName` | yes (one of them needed) | yes, in one `name` | yes, full text | yes, full text |
| `born` | yes | the year, with births and baptisms asked | - | - |
| `died` | yes | the year, with deaths and burials asked | - | - |
| `dated` | as the death date when `died` is not set | the year of the event | the dates the document covers | the dates of the file's series |
| `place` | the place of death | the place of the event | added to the full text | added to the full text |
| `country` | FR only | NL, BE, FR, SR | GB only | US only |
| `kinds` | must want DEATH | one kind sent, several sorted out after | must want OTHER | must want OTHER |
| `sex` | yes | - | - | - |
| `limit`, `page` | yes | yes (100 a page at most) | yes | yes |

`supports()` is false without a name for all four; matchID refuses a death looked for before 1970.
Each register's manual has the details.

## An example, live

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnistate\Exception\NotSupportedException;
use Omnistate\MatchId\MatchId;
use Omnistate\Model\CivilQuery;
use Omnistate\Model\CivilRecordKind;
use Omnistate\Model\Period;
use Omnistate\NationalArchivesUk\NationalArchivesUk;
use Omnistate\Omnistate;
use Omnistate\OpenArchieven\OpenArchieven;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
$omnistate = new Omnistate(civil: [new MatchId($http), new OpenArchieven($http), new NationalArchivesUk($http)]);

echo 'countries: ', implode(' ', $omnistate->civilCountries()), "\n";
foreach ($omnistate->civilRegistries() as $registry) {
    printf("  %-21s %-12s %-50s since %s, images: %s\n", $registry->name(), implode(',', $registry->countries()),
        implode(',', array_map(static fn (CivilRecordKind $kind) => $kind->value, $registry->kinds())),
        $registry->period() ?? '-', $registry->hasImages() ? 'yes' : 'no');
}

// France's deaths
$query = new CivilQuery(familyName: 'Chirac', givenName: 'Jacques', born: Period::year(1932), country: 'FR', kinds: [CivilRecordKind::DEATH], limit: 3);
foreach ($omnistate->civilRecords($query) as $record) {
    $person = $record->principal();
    printf("\n%s %s: %s, died %s in %s (%s), born %s in %s, act %s\n  %s\n  archive: %s\n",
        $record->source, $record->identifier, $person->name(), $record->date, $record->place, $record->place?->code,
        $person->birthDate, $person->birthPlace, $record->number, $record->url, $record->archive);
}

// The Netherlands: a line per person, then the whole act
$lines = $omnistate->civilRecords(new CivilQuery(familyName: 'van Gogh', givenName: 'Vincent Willem', dated: Period::year(1853), country: 'NL', kinds: [CivilRecordKind::BIRTH], limit: 2));
foreach ($lines as $line) {
    printf("\n%s %s: %s %s, %s, %s (%s)\n", $line->source, $line->identifier, $line->kind->value, $line->date, $line->place, $line->principal()?->fullName, $line->principal()?->role);
}
if ($lines) {
    $act = $omnistate->civilRecord($lines[0]->source, $lines[0]->identifier);
    foreach ($act->persons as $person) {
        printf("  %-10s %s\n", $person->role, $person->name());
    }
    printf("  archive: %s\n  %d image(s)\n", $act->archive, count($act->images));
}

// A question no register installed takes
try {
    $omnistate->civilRecords(new CivilQuery(familyName: 'Tanaka', country: 'JP'));
} catch (NotSupportedException $e) {
    echo "\n", $e->getMessage(), "\n";
}

// The UK: documents that name someone, kind OTHER
foreach ($omnistate->civilRecords(new CivilQuery(familyName: 'Smith', givenName: 'John', dated: Period::years(1770, 1780), country: 'GB', limit: 2)) as $paper) {
    printf("\n%s %s: %s\n  %s, period %s\n", $paper->source, $paper->identifier, $paper->title, $paper->archive, $paper->period);
}
```

```
countries: BE FR GB NL SR
  matchid               FR           death                                              since 1970/…, images: no
  openarchieven         NL,BE,FR,SR  birth,baptism,marriage,death,burial,other          since -, images: yes
  national-archives-uk  GB           other                                              since -, images: no

matchid fohpDS17K0Ez: Jacques Rene Chirac, died 2019-09-26 in Paris 6e Arrondissement, France (75106), born 1932-11-29 in Paris 5e Arrondissement, France, act 129
  https://deces.matchid.io/id/fohpDS17K0Ez
  archive: INSEE, fichier des personnes décédées, fichier 2019, ligne 505032

openarchieven ins:a28e3333-5b33-04af-7b55-c671109714f4: Jacques Rene Chirac, died 2019-09-26 in Paris (), born  in , act 
  https://www.openarchieven.nl/ins:a28e3333-5b33-04af-7b55-c671109714f4/en
  archive: L'Institut national de la statistique et des études économiques (INSEE)

openarchieven ins:154d0675-7401-d7eb-325f-746a8e958df7: Jean Jacques Marie Chirac, died 2000-08-08 in Clermont-Ferrand (), born  in , act 
  https://www.openarchieven.nl/ins:154d0675-7401-d7eb-325f-746a8e958df7/en
  archive: L'Institut national de la statistique et des études économiques (INSEE)

openarchieven bhi:78a37833-d731-ffe5-184c-f9f424826871: birth 1853-03-30, Zundert, Vincent Willem van Gogh (Child)

openarchieven wba:8759da72-f8d9-11df-a690-cd95c1e286e2: birth 1853-03-30, Zundert, Vincent Willem van Gogh (Child)
  Kind       Vincent Willem van Gogh
  Moeder     Anna Cornelia Carbentus
  Vader      Theodorus van Gogh
  archive: Brabants Historisch Informatie Centrum, Bron: boek, Deel: 9509, Periode: 1853, Geboorteregister Zundert 1853, n° 29
  1 image(s)

No civil registry installed takes that question.

national-archives-uk 2bf7ed32-739e-4cc8-b63d-2702b680aa96: John Smith
  Devon Archives and Local Studies Service (South West Heritage Trust), 1255 A/PO 31, period 1770

national-archives-uk C7378568: Smith, John
  The National Archives, Kew, ASSI 45/30/1/108-113, period 1771
```

(run on 2026-10-25 in the [harness](harness.md), against matchID, Open Archives and Discovery)

What the run shows:

- A question for France's deaths is one for two registers: matchID (INSEE's file) and Open
  Archives (which indexes the same INSEE deaths under the archive code `ins`). `civilRecords()`
  asks both and answers their records one after the other, each up to the query's `limit` - the
  same death can come twice, once from each.
- A search line of Open Archives is one person of one act: the whole name in `fullName`, the role
  as the archive writes it; `civilRecord()` gives the whole act, everyone named with their role in
  Dutch (`Kind`, `Moeder`, `Vader`), the archive's reference and the scans.
- A country no register holds (`JP`) is a `NotSupportedException`, before any request.
- Discovery's records are documents (kind `other`), dated by the years they cover.

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

`NotSupportedException`: no register installed takes the question (its country, its kinds, no
name). `OmnistateException`: a register refused the question itself (matchID on a date it cannot
read, Open Archives on an error it answers in the body).

With a cache, each register's answer to a query is kept under the query's `key()`, a record
found again under its register and identifier; an exception is never kept.

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

Every property, its type and default: [Concepts](concepts.md#civil-records-civilquery-civilrecord-civilperson-archive-place).

## Symfony

`OmnistateBundle` registers the civil register packages installed and tags them
`omnistate.civil_registry`; a class of yours implementing `CivilRegistryInterface` is tagged by
autoconfiguration and asked too.

```yaml
omnistate:
    matchid:
        token: '%env(MATCHID_TOKEN)%'   # optional
    nara:
        api_key: '%env(NARA_API_KEY)%'
```

## What is not there

- England and Wales' births, marriages and deaths: the General Register Office has no public API.
- The United States' births, marriages and deaths: kept by the states and counties, not by NARA;
  `omnistate/nara` is a catalogue of federal files, not an index of acts.
- Japan: the koseki are not public.
- Collaborative trees (FamilySearch, Geneanet) are not registers.
