---
title: Statistics
order: 35
---

# Statistics

What a state's statistical office, the EU's and a central bank publish as series: an index by
quarter, a rate by day, a price index by month. Three sources, each a package of its own:

| Package | Class | What | Ids | Access |
|---|---|---|---|---|
| `omnistate/insee` | `Insee\Insee` | INSEE's macro-economic database (BDM): every series of insee.fr's "indices et séries chronologiques" | an idBank (`001515333`) or a name: `IRL`, `ICC`, `ILC`, `ILAT`, `IPC` | open, no key; 30 calls a minute |
| `omnistate/eurostat` | `Eurostat\Eurostat` | Eurostat's datasets (API Statistics, JSON-stat) | a dataset and a code for each dimension: `prc_hicp_manr?freq=M&unit=RCH_A&coicop=CP00&geo=FR` | open, no key |
| `omnistate/ecb` | `Ecb\Ecb` | the ECB Data Portal (SDMX): exchange rates, interest rates, money, prices | a dataflow and its key: `EXR/D.USD.EUR.SP00.A`, or `EUR/USD`, `MRO` | open, no key |

All three speak SDMX or a JSON form of it, and give the same models.

```php
$omnistate = new Omnistate(statistics: [new Insee($http), new Eurostat($http), new Ecb($http)]);

$irl = $omnistate->series('insee:IRL', from: TimePeriod::quarter(2024, 1));
$irl->title;                         // "Indice de référence des loyers (IRL)"
$irl->latest()->period;              // 2026-Q3
$irl->observation('2026-Q2')->value; // 148.37
$irl->credit;                        // "Insee, BDM, série 001515333, extraction au 10/11/2026"

$omnistate->series('ecb:EUR/USD', last: 1)->latest()->value;        // 1.1193 (2026-11-09)
$omnistate->datasets('loyers', 'insee');                             // Dataset[]: IRL, INDICES_LOYERS...
```

## The contract

`Registry\StatisticsRegistryInterface`:

| Method | |
|---|---|
| `name()` | `insee`, `eurostat`, `ecb`: each `Series::$source` |
| `supports($id)` | whether the id is one it reads (each source's syntax above) |
| `series($id, ?TimePeriod $from, ?TimePeriod $to, ?int $last, bool $history)` | the series and its observations from `$from` to `$to` (both included), or its `$last` ones; with `$history`, every vintage (INSEE only: the others throw `NotSupportedException`); null when unknown |
| `datasets($query, $limit)` | the datasets of its catalogue whose id or title holds every word of the query |

The facade: `series($ref, ...)` takes `"<source>:<id>"`, or an id alone (the first source that
reads it); `datasets($query, ?$source, $limit)` asks one source or each; `indexChange($ref,
$from, $to)`; `statisticsRegistries()` and `statisticsRegistry($name)` give each source behind its
guard. Every call is counted under the source's policy ([policies](policies.md)): INSEE's 30
calls a minute are the BDM's terms.

## The models

- `Series`: `source`, `id`, `ref()`, `title` (and `titles` by language), `frequency`, `unit`,
  `unitMultiplier` (a power of ten), `base` (an index's base when the source gives it apart from
  the title), `dataset`, `updated`, `credit` (the source's citation, with the date of the
  extraction), `licence`, `url`, `dimensions` (the series' codes by SDMX dimension),
  `observations` (oldest first), `history`; `observation($period)` and `latest()` (the current
  vintage), `vintages($period)` (its revisions, the first published first).
- `Observation`: `period`, `value` (null when missing or confidential), `status`
  (`ObservationStatus`), `revised`, `published` (INSEE: its date in the Journal officiel),
  `vintage` (with history), `flags` (the source's own codes); `isProvisional()`.
- `TimePeriod`: a year, half-year, quarter, month, ISO week or day, written as SDMX writes them
  (`2026`, `2026-S1`, `2026-Q3`, `2026-09`, `2026-W05`, `2026-11-09`; INSEE's `2026-T3` read too);
  `start()`, `end()`, `shift()`, `next()`, `previous()`, `compare()`, `quarterOf($date)`.
- `Frequency` (SDMX's FREQ codes; INSEE's `T` read as quarterly), `ObservationStatus` (normal,
  provisional, semi-final, estimated, forecast, revised, break, low reliability, not significant,
  missing, confidential).
- `Dataset`: `source`, `id`, `title`, `updated`, `url`, `series` (how many, when the source says).
- `IndexChange`: two observations of an index; `ratio()`, `percent()`, `apply($amount)`
  (unrounded), `isProvisional()`, `credit`.

`Sdmx\Structures` (SDMX-ML structure messages: dataflows as `Dataset`s), `Sdmx\ObservationFlags`
(SDMX, INSEE and Eurostat flags as one status) and `Sdmx\Datasets` (the catalogue search) are what
the sources share.

## A lease's rent: the IRL

A residential lease in France is revised once a year by the IRL: the rent times the IRL of the
lease's reference quarter in the year of the revision, over the IRL of the same quarter a year
before (loi du 6 juillet 1989, art. 17-1). Omnistate gives the two published values and their
ratio; the rest is the lease's:

```php
// The lease's reference quarter (its clause; by default the last one published when it was signed).
$quarter = 2;
$change = $omnistate->indexChange('insee:IRL', TimePeriod::quarter(2025, $quarter), TimePeriod::quarter(2026, $quarter));

$change->from->value;     // 146.68 (2025-Q2)
$change->to->value;       // 148.37 (2026-Q2)
$change->ratio();         // 1.011522...
$rent = round($change->apply(850.00), 2);   // 859.79: rounding, caps and dates are the application's
$change->credit;          // to show beside it
```

`indexChange()` throws an `OmnistateException` when the later quarter is not published yet
("insee:001515333 has no value for 2026-Q4 yet."): the revision waits. The ILC and the ILAT
(commercial and professional leases) and the ICC are asked the same way (`insee:ILC`,
`insee:ILAT`, `insee:ICC`). What the core does not do, on purpose: which quarter a lease refers
to, the date it is revised on, a cap the law sets for a period, the rounding of an amount, and
keeping the values used - each is the lease's and the application's (omnibase/estate's).

## What history is

INSEE keeps every value as it was first published, then revised: `series(..., history: true)`
gives every vintage, each `Observation::$vintage` the date it was published from. The IRL is
never revised; consumer prices are, in their first months. Eurostat's API and the ECB's SDMX-JSON
keep no history: asking for one is a `NotSupportedException`.
