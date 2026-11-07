# Changelog — Personalization Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.0 — 2026-09-17

### Added

- Module-level `RULES.md` and `CHANGELOG.md` (lifeshifter-343). The module had them only on its view
  and its card. Records that preferences are per device, and that a preference needing the server
  belongs to the User module (lifeshifter-340, from lifeshifter 469603a).

### Fixed

- `MPersonalizationVisualCard` (1.1.2): the flat bordered card takes relief (`shadow-lift`), and its icon tile writes `primary-950` on the pale tint instead of `primary-700` (1.83:1).
