# Changelog — MLocalityFields

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.1 — 2026-09-17

### Fixed

- The "looking up the address" line is `text-muted`, not `text-subtle`. It is a sentence someone
  reads, and `subtle` only clears the 3:1 floor for icons (celer-06). The spec now asserts the tier.

## 1.0.0 — 2026-09-16

### Added

- First release in rnc (from chapaja). The city-level field block — optional CEP, city, UF
  and an optional free-text region behind `showRegion` — bound to one `LocalityFields` object,
  with ViaCEP filling city and UF on a complete CEP.
- Changed on the way, as in `MAddressFields`: `useId()` ids, a `for` on every label including the
  UF `Select`, `name` and `autocomplete` on the inputs, the lookup through `useCepLookup` with a
  "type it below" line on failure, a single root.
- `MLocalityFields.spec.ts` — the fields, the region toggle and its label, the fill that leaves the
  region alone, the unknown CEP, the unreachable ViaCEP and the incomplete CEP that asks nothing,
  all against MSW.
