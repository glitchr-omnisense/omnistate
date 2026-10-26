---
title: Companies
order: 31
---

# Companies

`company()` finds a company by its identifier, `search()` by its name. Both ask the company
registers installed - `omnistate/annuaire-entreprises` for France - and answer `Company` models
([Concepts](concepts.md#company)).

## A company by its identifier

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnistate\AnnuaireEntreprises\AnnuaireEntreprises;
use Omnistate\Omnistate;
use Symfony\Component\HttpClient\HttpClient;

$omnistate = new Omnistate(companies: [new AnnuaireEntreprises(HttpClient::create())]);

$company = $omnistate->company('356 000 000');        // a SIREN; a SIRET or FR..356000000 work too
printf("%s (%s), %s, VAT %s\n", $company->name, $company->identifier, $company->isActive() ? 'active' : 'closed', $company->vatNumber);
printf("  legal form %s (%s), activity %s, section %s, category %s\n", $company->legalForm, $company->legalForm?->code, $company->activity, $company->activity?->section, $company->category);
printf("  headcount %s (code %s, %s), created on %s\n", $company->headcount, $company->headcount?->code, $company->headcount?->year, $company->createdOn?->format('Y-m-d'));
printf("  head office %s, %s\n", $company->headOffice?->identifier, $company->headOffice?->address);
printf("  %d establishments, %d open\n", $company->establishmentCount, $company->openEstablishmentCount);
foreach (array_slice($company->managers, 0, 3) as $manager) {
    printf("  manager: %s%s\n", $manager->role, $manager->person ? ' (a person)' : ' (a company, '.$manager->identifier.')');
}
foreach (array_slice($company->finances, 0, 2) as $year) {
    printf("  %d: revenue %s, net income %s %s\n", $year->year, $year->revenue ?? '-', $year->netIncome ?? '-', $year->currency);
}
echo '  labels: ', implode(', ', $company->labels) ?: 'none', "\n";
echo '  collective agreements: ', implode(', ', $company->collectiveAgreements) ?: 'none', "\n";
```

```
LA POSTE (356000000), active, VAT FR39356000000
  legal form SA nationale à conseil d'administration (5510), activity 53.10Z, section H, category GE
  headcount 10000+ (code 53, 2024), created on 1991-01-01
  head office 35600000000048, DIRECTION GENERALE DE LA POSTE 9 RUE DU COLONEL PIERRE AVIA 75015 PARIS
  13131 establishments, 8862 open
  manager: Autre (a person)
  manager: Autre (a person)
  manager: Autre (a person)
  2025: revenue 26700000000, net income 1474000000 EUR
  labels: qualiopi, training_organization, finess, responsible_purchasing, alim_confiance, egapro
  collective agreements: 5516, 9999
```

(run on 2026-10-25 in the [harness](harness.md), against the Recherche d'entreprises API)

The identifier is normalized before it is asked: spaces, dots and dashes go, letters are upper
case, and a French VAT number whose last nine digits are a valid SIREN is looked up by that SIREN
(`FR39356000000` asks `356000000`). The facade then asks the first company register whose
`supports()` takes it; `omnistate/annuaire-entreprises` takes a SIREN or a SIRET whose key is
right. Anything else is a `NotSupportedException`, before any request:

```php
$omnistate->company('12345');         // NotSupportedException: No company registry installed knows "12345".
$omnistate->company('901821075');     // the same: a SIREN whose Luhn key is wrong
```

A company the register does not know is `null`.

## By SIRET: an establishment among the establishments

A SIRET gives the company, with the establishment asked for among its establishments;
`establishment()` finds it again by its identifier, whether it is the head office or another:

```php
$company = $omnistate->company('901 821 074 00019');
$establishment = $company->establishment('90182107400019');
$establishment->headOffice;   // true
$establishment->address;      // Address: street, postalCode, city, country, full, latitude, longitude
$establishment->status;       // CompanyStatus::ACTIVE
```

`Company::$establishments` holds those the register's answer included - for the Recherche
d'entreprises API, the establishments that matched the question - without the head office, which
is `headOffice`. `establishmentCount` and `openEstablishmentCount` are the register's counts of
all of them.

> [!WARNING]
> `Identifier\Siret::isValid()` refuses La Poste's head office as the register writes it
> (`35600000000048`): its La Poste rule exempts the NIC `00012` from the sum-of-digits check,
> while the register's head office is `00048`, which passes the plain Luhn check. So
> `$omnistate->company('35600000000048')` is a `NotSupportedException`; ask by the SIREN
> `356000000` instead. Observed on 2026-10-25; listed in [Verified, and not](verified.md).

## By name

```php
echo "\nSearch \"la poste\", 3 at most:\n";
foreach ($omnistate->search('la poste', 3) as $found) {
    printf("  %s %-10s %-45s %s\n", $found->identifier, $found->name, $found->legalForm?->label ?? $found->legalForm, $found->headOffice?->address?->city);
}
```

```
Search "la poste", 3 at most:
  356000000 LA POSTE   SA nationale à conseil d'administration      PARIS
  883262073 LA POSTE   Société civile immobilière                 COMMEQUIERS
  928695022 LA POSTE   Société civile immobilière                 SOURDEVAL
```

(the same run)

`search()` asks each company register in turn and answers the first non-empty list; with no
register installed, or none finding anything, it is `[]`. The query is passed as the register
reads it: the Recherche d'entreprises API searches names, addresses and identifiers alike, at most
25 a page. A search answers the same `Company` models as a lookup, each with its head office and
the establishments that matched.

For a form that suggests companies as one types, the Symfony bundle has a field and its endpoint:
[Company search](symfony.md#company-search).

## What a company holds

Everything the register gives is mapped, and `raw` keeps its whole answer:

| Property | From the Recherche d'entreprises API |
|---|---|
| `name`, `legalName`, `acronym` | `nom_complet`, `nom_raison_sociale`, `sigle` |
| `status` | `CLOSED` when `etat_administratif` is `C` |
| `legalForm` | INSEE's legal category, named from `LegalForms::FORMS` (45 common codes; another keeps its code without a name) |
| `activity` | the NAF rev. 2 code and its section |
| `category` | `PME`, `ETI`, `GE` |
| `headcount` | INSEE's range code, its bounds and year |
| `managers` | people (`person`, `firstNames`, `birthYear`, `nationality`) and companies (`identifier`: their SIREN) |
| `finances` | revenue and net income by year, newest first, in euros |
| `labels` | 17 flags of the answer's complements ([the list](https://github.com/glitchr-omnisense/omnistate-annuaire-entreprises/blob/1.x/docs/index.md#behaviour)) |
| `collectiveAgreements` | the IDCC codes |
| `vatNumber` | derived from the SIREN (`Siren::toVatNumber()`), VIES not asked |

The register's own manual has the whole mapping and its limits:
[omnistate/annuaire-entreprises](https://github.com/glitchr-omnisense/omnistate-annuaire-entreprises).

## Errors

- The register did not answer - HTTP 429 (with `retryAfter`), 5xx, 401/403, no answer, an answer
  that is not JSON: `UnavailableException`. That is not "no such company": ask again later.
- An identifier no register takes: `NotSupportedException`.
- Unknown: `null`; nothing found: `[]`.
