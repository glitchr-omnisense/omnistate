# omnisense/omnistate

Omnistate is one PHP contract for what states and public registers know. An application asks the
same questions whatever register answers them - who is this company, does this VAT number exist,
who holds this domain or this IP range, who is this health professional, which acts and documents
name this person - and gets the same models back: `Company`, `VatCheck`, `Domain`, `Network`,
`Professional`, `Facility`, `CivilRecord`. Each register is a package of its own
(`omnistate/annuaire-entreprises`, `omnistate/vies`...) that turns those questions into the
service's API calls.

```php
$omnistate->company('901 821 074');        // a SIREN, a SIRET or a French VAT number: Company
$omnistate->search('la poste', 5);         // Company[], by name
$omnistate->vat('IE6388047V');             // VatCheck: valid or not, whose, a consultation number
$omnistate->domain('example.com');         // Domain: registrar, dates, statuses, name servers
$omnistate->network('193.0.6.139');        // Network: an IP range or an AS number, its holder
$omnistate->professional('10003461033');   // Professional: an RPPS number
$omnistate->facility('754567860');         // Facility: a FINESS number
$omnistate->civilRecords(new CivilQuery(familyName: 'Chirac', born: Period::year(1932)));   // CivilRecord[]
```

The facade `Omnistate\Omnistate` holds the registers installed, asks the one that takes each
question, and keeps the answers in a cache when one is given (registers change slowly, and some
count their calls). Identifiers - SIREN, SIRET, EU VAT numbers, RPPS, FINESS, ISO country codes -
are checked in `Omnistate\Identifier\` without asking anyone. The core needs no framework: it
requires `symfony/http-client-contracts` and nothing else; each register requires
`symfony/http-client`, a library that stands alone. A Symfony bundle is included for applications
that want the wiring done, with validation constraints and a company search field
([Symfony](symfony.md)).

Published as `omnisense/omnistate`, formerly `glitchr/omnistate`: the package `replace`s the old
name, so a `composer.json` that still requires `glitchr/omnistate` resolves to this package and
never installs both. The namespace is `Omnistate\`; registers keep the `omnistate/` vendor and the
`Omnistate\<Service>\` namespace.

## When to use it

- Filling and checking a customer's or a supplier's company details (a SIREN typed, the rest
  filled in), and checking an intra-EU VAT number before a reverse-charge invoice, with VIES's
  consultation number kept as the proof.
- Knowing who stands behind a domain or an IP address (registrar, dates, holder, abuse contact).
- Checking a French health professional's RPPS or a facility's FINESS against the register.
- Searching acts and documents about a person across several countries' registers, each of which
  says beforehand what it covers.

It is not a data broker: each register is asked live, on the application's behalf, under the
service's own terms (see each register's Responsibility section). It stores nothing either, apart
from the optional cache: keeping a VAT check or a company's details is the application's job.

## Registers

Eight packages. The contracts are those each class implements; what each takes is what its
`supports()` answers (probed in the [harness](harness.md) on 2026-10-25 against the code at
HEAD).

| Package | Class | Contract | Takes | Access |
|---|---|---|---|---|
| [`omnistate/annuaire-entreprises`](https://github.com/glitchr-omnisense/omnistate-annuaire-entreprises) | `AnnuaireEntreprises\AnnuaireEntreprises` | company | a valid SIREN or SIRET; search by anything | open, no key |
| [`omnistate/vies`](https://github.com/glitchr-omnisense/omnistate-vies) | `Vies\Vies` | VAT | the 27 member states' numbers (EL for Greece) and XI (Northern Ireland) | open, no key |
| [`omnistate/iana`](https://github.com/glitchr-omnisense/omnistate-iana) | `Iana\Rdap` (with `Iana\Bootstrap`) | Internet | domain names, IPv4 and IPv6 addresses, AS numbers | open, no key |
| [`omnistate/annuaire-sante`](https://github.com/glitchr-omnisense/omnistate-annuaire-sante) | `AnnuaireSante\AnnuaireSante` | professional, facility | an RPPS (or IDNPS) number; a FINESS number | free key (ANS Gravitee portal) |
| [`omnistate/matchid`](https://github.com/glitchr-omnisense/omnistate-matchid) | `MatchId\MatchId` | civil | FR, deaths, since 1970, a name needed | open, no key (a token optional) |
| [`omnistate/openarchieven`](https://github.com/glitchr-omnisense/omnistate-openarchieven) | `OpenArchieven\OpenArchieven` | civil | NL, BE, FR, SR; births, baptisms, marriages, deaths, burials, other; scans | open, no key |
| [`omnistate/national-archives-uk`](https://github.com/glitchr-omnisense/omnistate-national-archives-uk) | `NationalArchivesUk\NationalArchivesUk` | civil | GB, catalogue entries (kind `other`) only | open, no key |
| [`omnistate/nara`](https://github.com/glitchr-omnisense/omnistate-nara) | `Nara\Nara` | civil | US, catalogue entries (kind `other`) only; links to scans | free key (10,000 calls a month) |

Five of the eight are open: no account and no key. Each register's own manual lists its
endpoints, options, mapping and limits.

## How this manual is organised

- [Installation](installation.md): requirements, a first script in plain PHP and its answer.
- [Symfony](symfony.md) and [Laravel](laravel.md): the facade in a framework.
- [Concepts](concepts.md): the facade, the six contracts, every model and enum.
- Usage: [companies](usage-companies.md), [VAT numbers](usage-vat.md),
  [domains and networks](usage-internet.md), [professionals and facilities](professionals.md),
  [civil registers](civil.md), [identifiers](usage-identifiers.md).
- [Configuration reference](configuration.md): the facade's arguments, each register's
  constructor, the bundle's tree.
- [Errors](errors.md): the three exceptions and the family's rule on them.
- [Testing](testing.md): testing an application that uses Omnistate; the conformance suite.
- [The Docker harness](harness.md): every register installed, a script that calls them.
- [Writing a register](providers.md), and its conformance suite.
- [Verified, and not](verified.md).
