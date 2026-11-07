# Changelog — MAddressFields

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.1 — 2026-09-17

### Fixed

- The "looking up the address" line is `text-muted`, not `text-subtle`. It is a sentence someone
  reads, and `subtle` only clears the 3:1 floor for icons (celer-06). The spec now asserts the tier.

## 1.0.0 — 2026-09-16

### Added

- First release in rnc (from chapaja). The full street-address field block, bound to one
  `AddressFields` object, with ViaCEP filling street, district, city and UF on a complete CEP.
- Changed on the way: ids come from `useId()` instead of being fixed, so two instances can share a
  page; every label has a `for`, including the UF `Select` (through `inputId`); the inputs carry
  `name` and `autocomplete`; the lookup goes through `useCepLookup`, which adds a "type it below"
  line when the CEP is unknown or ViaCEP is unreachable and drops a slow answer that a newer CEP
  overtook; the component has a single root.
- `MAddressFields.spec.ts` — the fields and their labels, distinct ids, the fill, the kept values
  on a blank answer, the loading line, the unknown CEP, the unreachable ViaCEP and the overtaken
  lookup, all against MSW.
