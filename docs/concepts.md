---
title: Concepts
order: 20
---

## Concepts

### The facade

`Omnistate\Omnistate` (final) holds the registers installed, by kind, and answers the family's
questions. Its arguments are in the [configuration reference](configuration.md#the-facade); what
each method does before it asks:

| Method | Returns | Asks | Before asking |
|---|---|---|---|
| `company(string $identifier): ?Company` | the company, `null` when the register does not know it | the first company register whose `supports()` takes the identifier | spaces, dots and dashes removed, upper case; a French VAT number (`FR` + key + a valid SIREN) becomes its SIREN; then `Siret::normalize()`, else `Siren::normalize()` |
| `search(string $query, int $limit = 10): array` | `Company[]` | each company register in turn, until one finds any | nothing: the query is passed as it is |
| `vat(string $number, ?string $requester = null): VatCheck` | the check | the first VAT register whose `supports()` takes the normalized number | `VatNumber::normalize()`; a number with no member state's shape, or a French one whose key disagrees with its SIREN, is answered at once: a `VatCheck` with `valid` false and `source` `format`, no register asked. The requester (the argument, else the facade's) is normalized too |
| `professional(string $identifier): ?Professional` | the professional, or `null` | the first professional register whose `supports()` takes it | spaces, dots and dashes removed; an IDNPS (`8` + RPPS) becomes its RPPS |
| `professionals(string $name, ?string $postcode = null, int $limit = 10): array` | `Professional[]` | each professional register in turn, until one finds any | nothing |
| `facility(string $identifier): ?Facility` | the facility, or `null` | the first professional register that implements `FacilityRegistryInterface` and whose `supportsFacility()` takes it | `Finess::normalize()`, else spaces, dots and dashes removed and upper case |
| `civilRecords(CivilQuery $query, ?string $registry = null): array` | `CivilRecord[]` | every civil register whose `supports()` takes the query (only the one named `$registry` when given), one after the other in the order given | nothing: the query object carries its own normalization |
| `civilRecord(string $registry, string $identifier): ?CivilRecord` | the record, or `null` | the civil register of that name, its `find()` | nothing |
| `civilRegistries(?string $country = null, ?CivilRecordKind $kind = null): array` | `CivilRegistryInterface[]` | no one: the registers' own declarations | the country upper-cased and trimmed |
| `civilRegistry(string $name): ?CivilRegistryInterface` | the register of that name, `null` when it is not installed | no one | - |
| `civilCountries(): array` | ISO 3166-1 alpha-2 codes some civil register covers, sorted | no one | - |
| `domain(string $name): ?Domain` | the domain, or `null` | the Internet registry | trimmed, lower case, the trailing dot removed |
| `network(string $address): ?Network` | the IP range or the autonomous system, or `null` | the Internet registry | trimmed, upper case (`as3333` is `AS3333`) |

What it throws:

- `NotSupportedException` when no register installed takes the question: no company register
  supports that identifier (`No company registry installed knows "X".`), no VAT register answers
  for that country, no professional or facility register knows that identifier, no civil register
  takes the query (`No civil registry installed takes that question.`, or `The civil registry "x"
  is not installed, or does not take that question.` when one was named), `civilRecord()` of a
  register not installed, `domain()` / `network()` with no Internet registry (`No Internet
  registry installed: add omnistate/iana.`). `search()` and `professionals()` never throw it: with
  no register installed they answer `[]`.
- Whatever the register throws: `UnavailableException` when it did not answer, sometimes an
  `OmnistateException` when it refused the question ([Errors](errors.md)).

`civilRecords()` without a register name asks every register the question is for: one that does
not answer throws, and the others' answers are lost with it. A screen that must keep what the
others found asks them one by one ([Civil registers](civil.md#a-register-that-is-down)).

Answers are kept in the cache when one is given: every method that asks a register, under a key
made of the method, the register's name and the normalized question
([Configuration](configuration.md#the-cache)). `civilRegistries()`, `civilRegistry()` and
`civilCountries()` ask no one and are not cached.

### The six contracts

A register implements one or more interfaces of `Omnistate\Registry\`. Each has `name(): string`,
a short name in kebab case (`annuaire-entreprises`) that is the `$source` of every model it
returns.

#### CompanyRegistryInterface

A register of companies.

| Method | |
|---|---|
| `supports(string $identifier): bool` | whether it can answer for that identifier (a SIREN, a SIRET...) - checked without a request |
| `company(string $identifier): ?Company` | the company, with the establishment asked for among its establishments when the identifier is one; `null` when the register does not know it |
| `search(string $query, int $limit = 10): array` | `list<Company>` by name, or anything the register searches on |

#### VatRegistryInterface

A register of VAT numbers.

| Method | |
|---|---|
| `supports(string $number): bool` | whether it answers for that (normalized) number's country |
| `check(string $number, ?string $requester = null): VatCheck` | whether the number exists, and whose; with `$requester` (your own VAT number) the register may give a consultation number |

A check that cannot be made is an `UnavailableException`, never a `VatCheck` saying "not valid".

#### ProfessionalRegistryInterface

A register of regulated professionals.

| Method | |
|---|---|
| `supports(string $identifier): bool` | whether it can answer for that identifier (an RPPS number...) |
| `professional(string $identifier): ?Professional` | the professional, `null` when unknown |
| `search(string $name, ?string $postcode = null, int $limit = 10): array` | `list<Professional>` by name, only those practising in that postcode when one is given |

#### FacilityRegistryInterface

A register of health and social facilities. The facade asks it only of the professional
registers that implement it too.

| Method | |
|---|---|
| `supportsFacility(string $identifier): bool` | whether it can answer for that identifier (a FINESS number...) |
| `facility(string $identifier): ?Facility` | the facility, `null` when unknown |

#### CivilRegistryInterface

A register of acts and documents about people. It says what it covers, so a screen can tell
before asking.

| Method | |
|---|---|
| `countries(): array` | `list<string>`, ISO 3166-1 alpha-2 codes of the countries whose records it holds |
| `kinds(): array` | `list<CivilRecordKind>` it holds |
| `period(): ?Period` | the years it covers when it has bounds (France's deaths file: `Period::since(1970)`), `null` when there is no bound to tell |
| `hasImages(): bool` | whether it gives pictures of the acts (`CivilRecord::$images`) for some records |
| `supports(CivilQuery $query): bool` | whether the question is one for it: its country, one of its kinds, something it can search on |
| `search(CivilQuery $query): array` | `list<CivilRecord>`, the likeliest first; none is `[]` |
| `find(string $identifier): ?CivilRecord` | a record by the identifier a search gave, with everything the register tells; `null` when unknown |

#### InternetRegistryInterface

The Internet's registries.

| Method | |
|---|---|
| `domain(string $name): ?Domain` | a domain name's registration, `null` when no one registered it |
| `network(string $address): ?Network` | an IP address's range, or an AS number's (`AS3215`, `3215`) |

Every method that asks may throw `UnavailableException`; none returns `null` or `[]` for a
register that did not answer ([the family's rule](errors.md#the-familys-rule)).

### Models

All models are `final readonly` classes in `Omnistate\Model`, built with named arguments.
Whatever a register cannot tell is `null` or an empty list. Countries are ISO 3166-1 alpha-2,
dates `\DateTimeImmutable` unless they may be partial (`PartialDate`), amounts in the currency's
major unit. The models that carry `$raw` keep the register's whole answer for what they leave out.

#### Company

| Property | Type | Default | |
|---|---|---|---|
| `identifier` | `string` | required | the register's identifier (France: the SIREN) |
| `name` | `string` | required | the name in use |
| `status` | `CompanyStatus` | required | active or closed |
| `country` | `string` | required | ISO 3166-1 alpha-2 |
| `source` | `string` | required | the register's name: `annuaire-entreprises` |
| `legalName` | `?string` | `null` | the registered name (*raison sociale*) |
| `acronym` | `?string` | `null` | |
| `legalForm` | `?LegalForm` | `null` | |
| `activity` | `?Activity` | `null` | the main activity |
| `category` | `?string` | `null` | the size category (France: PME, ETI, GE) |
| `headcount` | `?Headcount` | `null` | |
| `createdOn`, `closedOn` | `?\DateTimeImmutable` | `null` | |
| `headOffice` | `?Establishment` | `null` | |
| `establishments` | `list<Establishment>` | `[]` | those the answer included (all, or the ones that matched), the head office not repeated |
| `establishmentCount`, `openEstablishmentCount` | `?int` | `null` | how many the register counts, open or not |
| `managers` | `list<Manager>` | `[]` | |
| `finances` | `list<FinancialYear>` | `[]` | newest first |
| `labels` | `list<string>` | `[]` | what it is recognized as: `ess`, `rge`, `qualiopi`... ([the list](https://github.com/glitchr-studio/omnistate-annuaire-entreprises/blob/1.x/docs/index.md#behaviour)) |
| `collectiveAgreements` | `list<string>` | `[]` | the collective agreements it applies (France: IDCC codes) |
| `vatNumber` | `?string` | `null` | its VAT number (France: derived from the SIREN, VIES not asked) |
| `updatedAt` | `?\DateTimeImmutable` | `null` | when the register last changed it |
| `raw` | `array` | `[]` | |

`isActive(): bool` is `status === CompanyStatus::ACTIVE`. `establishment(string $identifier):
?Establishment` finds the head office or one of `establishments` by its identifier.

#### Establishment

One of a company's places of business (France: a SIRET).

| Property | Type | Default | |
|---|---|---|---|
| `identifier` | `string` | required | the SIRET |
| `headOffice` | `bool` | required | whether it is the head office |
| `status` | `CompanyStatus` | required | |
| `address` | `?Address` | `null` | |
| `name` | `?string` | `null` | its trade name |
| `signs` | `list<string>` | `[]` | the signs it trades under |
| `activity` | `?Activity` | `null` | |
| `headcount` | `?Headcount` | `null` | |
| `createdOn`, `closedOn` | `?\DateTimeImmutable` | `null` | |

`isActive(): bool`.

#### Address

| Property | Type | Default | |
|---|---|---|---|
| `street` | `?string` | `null` | number, type and name of the street |
| `postalCode` | `?string` | `null` | |
| `city` | `?string` | `null` | |
| `country` | `?string` | `null` | ISO 3166-1 alpha-2 |
| `full` | `?string` | `null` | the register's own one-line form |
| `latitude`, `longitude` | `?float` | `null` | |

`(string) $address` is `full`, else street, postal code and city joined by spaces.

#### Activity, LegalForm, Headcount, Manager, FinancialYear

| Class | Properties | `__toString()` |
|---|---|---|
| `Activity` | `code` (`62.02A`), `classification` (`NAF rev. 2`), `?label`, `?section` (the NAF's letter: `J`) | the code, and the label when there is one |
| `LegalForm` | `code` (INSEE's `5499`), `?label`, `?short` (`SARL`) | the short name, else the label, else the code |
| `Headcount` | `?min`, `?max` (`null`: no upper bound), `?year` (when it was counted), `?code` (INSEE's: `12` for 20 to 49) | `''` when no minimum, `10000+`, `5`, `20-49` |
| `Manager` | `name`, `?role` (`Gérant`), `person` (bool, default `true`; `false` for a company), `?firstNames`, `?identifier` (a managing company's SIREN), `?birthYear`, `?nationality` | first names and name |
| `FinancialYear` | `year`, `?revenue`, `?netIncome`, `currency` (default `EUR`) | - |

`Headcount::isEmployer()` is true when `max` is set and above 0.

#### VatCheck

| Property | Type | Default | |
|---|---|---|---|
| `number` | `string` | required | the number checked, normalized (`EL...` for Greece) |
| `valid` | `bool` | required | whether it exists |
| `checkedAt` | `\DateTimeImmutable` | required | the register's date of the check (VIES's `requestDate`), else now |
| `source` | `string` | required | `vies`, or `format` when the facade refused the number for its shape without asking |
| `name`, `address` | `?string` | `null` | whose it is, when the member state tells (address lines joined by `, `) |
| `consultationNumber` | `?string` | `null` | the proof of the check, when it was made with your own VAT number: keep it with a reverse-charge invoice |

`country(): string` is the number's first two letters.

#### Domain and Network

| `Domain` property | Type | |
|---|---|---|
| `name` | `string` | lower case, ASCII form for an international name |
| `source` | `string` | `iana` |
| `handle` | `?string` | the registry's handle |
| `statuses` | `list<string>` | RDAP statuses: `active`, `client transfer prohibited`... |
| `registrar` | `?Contact` | |
| `registrant` | `?Contact` | `null` when the registry does not publish it (most, since the GDPR) |
| `registeredAt`, `expiresAt`, `changedAt` | `?\DateTimeImmutable` | the `registration`, `expiration` and `last changed` events |
| `nameservers` | `list<string>` | lower case |
| `dnssec` | `bool` | whether the delegation is signed (default `false`) |
| `server` | `?string` | the RDAP server that answered |
| `raw` | `array` | |

| `Network` property | Type | |
|---|---|---|
| `handle` | `string` | the registry's handle (`AS3333`, `193.0.0.0 - 193.0.7.255`) |
| `source` | `string` | `iana` |
| `name` | `?string` | `RIPE-NCC` |
| `type` | `?string` | `ALLOCATED PA`, `ASSIGNED PA`, `DIRECT ALLOCATION`... |
| `country` | `?string` | |
| `startAddress`, `endAddress` | `?string` | an IP range's first and last address |
| `startAutnum`, `endAutnum` | `?int` | an autonomous system's numbers |
| `registrant`, `abuse` | `?Contact` | the holder, the abuse contact |
| `registeredAt`, `changedAt` | `?\DateTimeImmutable` | |
| `server` | `?string` | |
| `raw` | `array` | |

`Contact`: `?name`, `?organization`, `?email`, `?country`, `?handle`, `?ianaId` (a registrar's
IANA ID), `?url`. `(string) $contact` is the organization, else the name, else the handle.

#### Professional, Workplace, Facility, Qualification

| `Professional` property | Type | Default | |
|---|---|---|---|
| `identifier` | `string` | required | France: the RPPS number |
| `familyName` | `string` | required | |
| `source` | `string` | required | `annuaire-sante` |
| `givenName` | `?string` | `null` | |
| `prefix` | `?string` | `null` | `M`, `MME`, `DR`... as the register writes it |
| `profession` | `?Qualification` | `null` | |
| `specialties` | `list<Qualification>` | `[]` | specialties and know-how |
| `diplomas` | `list<Qualification>` | `[]` | |
| `workplaces` | `list<Workplace>` | `[]` | active ones first |
| `phones`, `emails` | `list<string>` | `[]` | |
| `secureEmails` | `list<string>` | `[]` | secure messaging (France: MSSanté) |
| `languages` | `list<string>` | `[]` | ISO 639-1 |
| `active` | `bool` | `true` | |
| `updatedAt` | `?\DateTimeImmutable` | `null` | |
| `raw` | `array` | `[]` | |

`name()` (and `(string)`) is the given name and the family name.

`Workplace`: `?name`, `?address` (`Address`), `?facility` (the facility's identifier: FINESS, or
the register's structure id), `?mode` (`liberal`, `salaried`, `volunteer`, or the register's own
code), `?role`, `phones`, `secureEmails`, `active` (default `true`). `(string)` is the name and
the address.

`Facility`: `identifier` (FINESS), `name`, `source`, `?kind` (`legal`: the entity, `site`: one of
its places), `types` (`list<Qualification>`), `?address`, `phones`, `emails`, `secureEmails`,
`identifiers` (every identifier the register gives it), `active` (default `true`), `?updatedAt`,
`raw`. `(string)` is the name.

`Qualification`: `code`, `system` (the nomenclature: a URL or a short name,
`TRE_G15-ProfessionSante`), `?label`. `(string)` is the label, else the code.

#### Civil records: CivilQuery, CivilRecord, CivilPerson, Archive, Place

`CivilQuery` is what is asked of a civil register:

| Argument | Type | Default | |
|---|---|---|---|
| `familyName`, `givenName` | `?string` | `null` | |
| `born`, `died` | `?Period` | `null` | when the person was born, died |
| `dated` | `?Period` | `null` | when the event recorded took place, whatever it is |
| `place` | `?string` | `null` | the town of the event |
| `country` | `?string` | `null` | the country whose registers are asked; trimmed, upper case, `''` is `null` |
| `kinds` | `list<CivilRecordKind>` | `[]` | the kinds wanted; empty: any |
| `sex` | `?Sex` | `null` | |
| `limit` | `int` | `20` | kept between 1 and 100 |
| `page` | `int` | `1` | at least 1 |

Its methods: `name()` (given and family name), `hasName()`, `wants(CivilRecordKind)` (true when
`kinds` is empty or holds it), `wantsAny(...$kinds)`, `inCountries(...$countries)` (true when no
country is asked or it is one of them), `key()` (the same question gives the same key: the cache
uses it). A register uses what it can search on and ignores the rest; each register's manual says
which fields it reads.

`CivilRecord`:

| Property | Type | Default | |
|---|---|---|---|
| `identifier` | `string` | required | what `civilRecord()` takes to find it again in its register |
| `kind` | `CivilRecordKind` | required | |
| `source` | `string` | required | the register's name |
| `date` | `?PartialDate` | `null` | of the event, not of the paper |
| `place` | `?Place` | `null` | |
| `persons` | `list<CivilPerson>` | `[]` | the one the record is about first |
| `archive` | `?Archive` | `null` | who keeps the original |
| `url` | `?string` | `null` | the record's page at the register |
| `images` | `list<string>` | `[]` | scans of the act, when the register publishes them |
| `title` | `?string` | `null` | what the register calls it |
| `number` | `?string` | `null` | the act's number in its register |
| `description` | `?string` | `null` | |
| `period` | `?Period` | `null` | when the register dates it by a span rather than a day |
| `raw` | `array` | `[]` | |

`principal(): ?CivilPerson` is the first person; `hasImages(): bool`.

`CivilPerson`: `familyName` (default `''`), `givenNames` (`list<string>`), `sex` (default
`Sex::UNKNOWN`), `?role` (as the register writes it: `Child`, `Vader`, `Deceased`...),
`?birthDate`, `?birthPlace`, `?deathDate`, `?deathPlace`, `?age` (at the event), `?profession`,
`?fullName` (when the register gives the name in one piece only). `name()` is the given names and
the family name, else `fullName`; `givenName()` the first given name.

`Archive`: `name` (`Brabants Historisch Informatie Centrum`), `?code` (the register's code for
it), `?reference` (the call number), `?url` (the record at the archive). `(string)` is the name
and the reference.

`Place`: `?name` (the town as written), `?code` (France: the INSEE commune code), `?postcode`,
`?region` (a département's code, a province), `?country` (ISO alpha-2), `?countryName` (as
written, when no code is known), `?latitude`, `?longitude`. `(string)` is the name and the
country.

#### PartialDate and Period

`PartialDate` is a date as a register knows it: `?year`, `?month` (1 to 12), `?day`. What is not
known is `null`, never a made-up 1st of January.

| Method | |
|---|---|
| `parse(?string $value): ?self` | reads `1932-11-29`, `19321129`, `29/11/1932`, `29.11.1932`, `1932-11`, `11/1932`, `1932`, and zeros as unknown parts (`19320000`); `null` when no year can be read |
| `of(?int $year, ?int $month, ?int $day): ?self` | zeros and out-of-range parts become unknown; no year, `null`; a day without a month is dropped |
| `fromDate(\DateTimeInterface $date): self` | a whole date |
| `isComplete(): bool` | year, month and day known |
| `toDate(): ?\DateTimeImmutable` | the day itself when it is whole and exists, else `null` |
| `earliest()`, `latest()` | the first and the last day it can be (`1932` is 1932-01-01 to 1932-12-31) |
| `(string)` | ISO 8601 as far as it is known: `1932-11-29`, `1932-11`, `1932` |

`Period` is a span between two `PartialDate`s, either open: `?from`, `?to`.

| Method | |
|---|---|
| `year(int $year)` | one year, at both ends |
| `years(?int $from, ?int $to)` | from a year to a year, either `null` |
| `since(int $year)` | open at the end |
| `around(int $year, int $margin = 2)` | a year give or take `$margin` |
| `day(PartialDate $date)` | one date at both ends |
| `isDay()`, `isYear()` | |
| `contains(PartialDate $date): bool` | whether the date can fall in it |
| `(string)` | `1932`, `1970/…`, `1850/1860` |

#### Enums

| Enum | Cases |
|---|---|
| `CompanyStatus` (string) | `ACTIVE` (`active`), `CLOSED` (`closed`) |
| `CivilRecordKind` (string) | `BIRTH` (`birth`), `BAPTISM` (`baptism`), `MARRIAGE` (`marriage`), `DEATH` (`death`), `BURIAL` (`burial`), `OTHER` (`other`: a population register, a notarial deed, a catalogue entry of an archive) |
| `Sex` (string) | `MALE` (`M`), `FEMALE` (`F`), `UNKNOWN` (`U`) |

`CivilRecordKind::gedcom()` is the GEDCOM tag of the event: `BIRT`, `CHR`, `MARR`, `DEAT`, `BURI`,
`null` for `OTHER`. `Sex::fromAny(mixed)` reads what registers write: `M`, `male`, `man`, `h`,
`homme`, `masculin`, `1` are `MALE`; `F`, `female`, `vrouw`, `femme`, `féminin`, `feminin`, `w`,
`2` are `FEMALE` (case ignored); anything else `UNKNOWN`.
