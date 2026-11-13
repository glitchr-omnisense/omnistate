# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

### Added
- Statistics, the family's new capability (Marco, 2026-11-10: in omnistate, no new family):
  `Registry\StatisticsRegistryInterface` (`supports()`, `series($id, $from, $to, $last, $history)`,
  `datasets($query, $limit)`), the models `Series`, `Observation`, `TimePeriod` (year, half-year,
  quarter, month, ISO week, day, as SDMX writes them), `Frequency`, `ObservationStatus`, `Dataset`
  and `IndexChange` (two values of an index: `ratio()`, `percent()`, `apply()`); the facade's
  `series($ref)` ("insee:IRL", or an id alone), `indexChange()`, `datasets()`,
  `statisticsRegistries()`, `statisticsRegistry()` (`Policy\GuardedStatisticsRegistry`), its
  `statistics:` argument (last), the bundle's `omnistate.statistics_registry` tag;
  `Sdmx\Structures`, `Sdmx\ObservationFlags`, `Sdmx\Datasets`, what the sources share. The
  first sources: omnistate/insee, omnistate/eurostat, omnistate/ecb. `RegistryContractTests`
  checks a statistics source too (`seriesIdentifier()`, `datasetQuery()`). docs/statistics.md,
  with a lease's revision by the IRL.
- `Http\JsonClient::answer()` takes the error statuses to give back (`$pass`).
- The harness installs a source not on GitHub yet from its checkout only (plugins.php's fourth
  element), and `bare` asks the IRL.
- On omnisense/contracts, the kernel of the omni families, with usage policies. Every call the
  facade makes to a register goes through its policy's guard (`Policy\Guards`): the policy it
  declares (`DeclaresUsagePolicyInterface`, its `policy.json`), tightened by the site's
  (`policies:`, by register name, never looser), checked against what the site is (`usage:`); a
  call over a limit is never made (`Exception\UsageLimitReachedException`, an
  `UnavailableException` with `$retryAfter`, `$provider` and `$limit`), a register's 429 holds
  every call to it for its `$retryAfter`, an answer kept in the cache is not a call and is kept no
  longer than the policy allows. `limiter:` counts across processes. `Omnistate::policy()`,
  `policies()`, `guard()`; `civilRegistries()` and `civilRegistry()` give each register behind
  its guard (`Policy\GuardedCivilRegistry`, `inner()`).
- `UnavailableException::$status` (a fourth, optional constructor argument) and
  `isTooManyCalls()`; the registers pass 429 on a 429.
- `Http\JsonClient`: omnisense/contracts' JSON client with the family's rule on errors (401, 403,
  429, 5xx, no answer and an unreadable body an `UnavailableException`, another 4xx an
  `OmnistateException`); `answer()` gives any answer (XML too), null on 404, a 429 with its
  Retry-After.
- `Testing\RegistryContractTests::testItsUsagePolicyIsDeclaredAndHonoured()`: the policy reads and
  names the register; a site's tighter limit and a 429 refuse the next call with no request.
- PHPStan at level max (`phpstan.neon`), its baseline the 103 findings of 2026-11-10; the harness
  runs it (`phpstan`) and installs omnisense/contracts from its checkout
  (`OMNISENSE_CONTRACTS`) or GitHub.

### Changed
- Requires `omnisense/contracts: ^1.0`: stable versions, so `composer require omnisense/omnistate` installs in a project at the default minimum-stability. `extra.branch-alias` names 1.x (`1.x-dev`); the CI (`.github/workflows/ci.yml`, glitchr-studio/ci's shared workflow) and the release on a tag (`release.yml`).
- The package passes its CI alone, without the sources: the bundle checks each statistics source and IANA's `Bootstrap` with a `class_exists()` of its own (PHPStan knew none of them), and the two bundle tests that need omnistate/vies or omnistate/annuaire-sante are skipped without them (the harness, which installs every source, still runs them).
- PHPStan at level max with an empty baseline, types only: the models' `$raw` is `array<mixed>`,
  `CompanySearch::row()` its row's shape (`CompanyRow`), `CivilQuery`'s `$kinds` any array of kinds
  (listed by it). A validator given an object that is not `Stringable`, and `Sex::fromAny()` given
  one, read it as an empty string (a violation, `Sex::UNKNOWN`) instead of throwing PHP's `Error`.
  `RegistryContractTests` hands the facade a facility register as a professional one only when it
  is one (`ProfessionalRegistryInterface`, as `Omnistate`'s `professionals:` takes them).
- `OmnistateException` implements omnisense/contracts' `ExceptionInterface`.
- `Http\Redaction` is omnisense/contracts' under the family's name: the same methods.
- `Omnistate\Http\Redaction`: a request's secrets (`secrets()`), a text without them (`text()`), a
  failure's copy to wrap in its place (`copy()`), a trace without arguments (`withoutArguments()`),
  for the registers that call with a key or a token.

### Fixed
- `Siret::isValid()` accepts La Poste's head office, `35600000000048`: a La Poste SIRET is valid
  when it passes the Luhn check or, La Poste's own rule, when its digits sum to a multiple of 5.
  The sum rule alone was applied to it (but for the NIC `00012`), so the head office, which passes
  Luhn only, was refused: `company('35600000000048')` was a `NotSupportedException` and the
  `#[Siret]` constraint refused it.
