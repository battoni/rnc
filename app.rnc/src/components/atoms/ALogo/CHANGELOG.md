# Changelog — ALogo

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.1.0 — 2026-11-07

### Changed

- `tone="brand"` paints `text-brand-word` instead of `text-primary-500`: on the dark scheme the navy
  step sat on the near-black header at 1.6:1 and the public site opened without its mark. The theme's
  `--color-primary-text` names the step that reads on each ground (rnc-1).

## 1.2.0 — 2026-09-06

### Changed

- Points at `.claude/rules/celer-09-brand-mark-contract.md` for how a mark BEHAVES, and keeps only
  this project's implementation. This unit is `LOCAL` and never leaves, so every rule written here
  was a rule no other project could ever read — lifeshifter's copy grew to 148 lines and none of
  it could travel. The contract file is `OVERWRITE` and does.
- Says outright that this is a raster mark and implements `variant` alone, so the absent motion
  props read as a decision rather than as a gap.

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`ALogo.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
