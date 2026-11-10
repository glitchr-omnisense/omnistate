# Changelog

## Unreleased (1.x)

### Added
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
