# Changelog — MPermissionPanel

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.2.1 — 2026-09-17

### Fixed

- Each permission switch had `ariaLabel="{label} permission"`, which overrode its visible label and read an English word in pt-BR too. The switch is now named by its `<label for>` alone (new-impressao-084, lifeshifter-320). The spec asserts no `aria-label` on those inputs.
- The group icon took `text-ink-950` (a raw ramp) on `bg-primary-100`; it takes `text-primary-950`, as the nav does (CEL-20). Asserted in the spec.

### Changed

- `PermissionGroup` is imported from `@AuthModule`, not from `views/RolesAndPermissions/interfaces` (CEL-12).

## 1.2.0 — 2026-09-14

### Fixed

- Every `<label for>` pointed at the switch ROOT, a `<div>` that a click cannot toggle. The id goes through `inputId`, onto the real `<input>` (from lifeshifter).

### Added

- `MPermissionPanel.spec.ts` (from lifeshifter), including that each label reaches its input.

### Changed

- `rounded-lg`, not `rounded-2xl`: a panel is a card on the radius scale, and `2xl` is not on it (from lifeshifter).

### Fixed

- The group name wraps instead of truncating: in the three-column grid every panel read "pe…" (from lifeshifter).

## 1.1.0 — 2026-06-13

### Changed

- Migrate to scheme-aware role tokens.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
