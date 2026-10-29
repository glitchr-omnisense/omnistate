# Changelog

## Unreleased (1.x)

### Fixed
- `Siret::isValid()` accepts La Poste's head office, `35600000000048`: a La Poste SIRET is valid
  when it passes the Luhn check or, La Poste's own rule, when its digits sum to a multiple of 5.
  The sum rule alone was applied to it (but for the NIC `00012`), so the head office, which passes
  Luhn only, was refused: `company('35600000000048')` was a `NotSupportedException` and the
  `#[Siret]` constraint refused it.
