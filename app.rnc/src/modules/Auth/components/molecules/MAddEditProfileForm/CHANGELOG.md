# Changelog — MAddEditProfileForm

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.0 — 2026-09-17

### Fixed

- Every failed save raised the generic "something went wrong" toast, including the 422 the API sends for a taken name. A 422 is now shown where it belongs: `errors.name` under the name field (`rolesAndPermissions.form.nameTaken`), any other key in `AFormError`, and no toast. Only a failure that is not a 422 raises the error toast (celer-07, arcus-auth ARC-13).
- Footer buttons `gap-2` → `gap-3` (VIS-15), and the permission-groups `MultiSelect` takes `inputId`, not `id` (lifeshifter-471).

### Added

- Specs for the three refusal paths and the footer gap, each seen failing first.

## 1.0.2 — 2026-09-16

### Changed

- The spec's profile fixture uses a numeric id (from lifeshifter-269).

## 1.0.1 — 2026-09-14

### Fixed

- The sticky save bar has a ground (`bg-canvas`), so the fields scrolling under it no longer show through. Every other form's save bar already had one (from new.impressao).
- Edit mode pre-selects only the groups the profile holds (`isHeld`). The role endpoint returns every declared group now, and the picker showed all of them as the profile's; covered by `MAddEditProfileForm.spec.ts`.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
