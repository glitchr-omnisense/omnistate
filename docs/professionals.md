# Regulated professionals and facilities

`Omnistate\Registry\ProfessionalRegistryInterface` is the one contract for the registers of regulated
professions: France's health professionals (`omnistate/annuaire-sante`, the RPPS), later its lawyers
(a future `omnistate/cnb`). Whoever reads a professional need not know which profession it is.

```php
$omnistate->professional('10003461033');              // an RPPS number, or its IDNPS form 810003461033
$omnistate->professionals('Martin', postcode: '67');  // by name, practising in that postcode
$omnistate->facility('670001234');                    // a FINESS number
```

## The models

| Model | What it holds |
|---|---|
| `Professional` | `identifier`, `familyName`, `givenName`, `prefix`, `profession` and `specialties` and `diplomas` (`Qualification`: code, nomenclature, label), `workplaces`, `phones`, `emails`, `secureEmails` (MSSanté), `languages`, `active`, `updatedAt`, `raw` |
| `Workplace` | where and how one practises: `name`, `address`, `facility` (its FINESS), `mode` (`liberal`, `salaried`, `volunteer`), `role`, `phones`, `secureEmails`, `active` |
| `Facility` | `identifier` (FINESS), `name`, `kind` (`legal` entity or `site`), `types`, `address`, `phones`, `emails`, `secureEmails`, `identifiers`, `active` |

A register that knows facilities too implements `FacilityRegistryInterface` (`supportsFacility()`,
`facility()`); `Omnistate::facility()` asks the professional registries that do.

## Identifiers, checked without asking anyone

```php
Rpps::isValid('10003461033');      // eleven digits, Luhn key
Rpps::normalize('810003461033');   // "10003461033": the IDNPS is "8" + RPPS
Rpps::toIdnps('10003461033');      // "810003461033", what Pro Santé Connect and the Annuaire Santé call it
Rpps::withKey('1000000001');       // "10000000017": a valid fictional number, for fixtures
Finess::isValid('580008803');      // nine characters, Luhn key; 2A / 2B for Corsica
```

The `#[Rpps]` and `#[Finess]` constraints check the same.

## Symfony

```yaml
omnistate:
    annuaire_sante:
        api_key: '%env(ESANTE_API_KEY)%'   # empty: every call throws UnavailableException
```

Registries of your own implementing `ProfessionalRegistryInterface` are tagged
`omnistate.professional_registry` and asked in turn.

A registry that does not answer throws `UnavailableException`: that is not "no such professional".
