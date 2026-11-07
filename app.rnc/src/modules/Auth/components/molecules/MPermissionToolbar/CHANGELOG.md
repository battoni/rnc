# Changelog — MPermissionToolbar

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.1.0 — 2026-09-17

### Added

- `canUpdate` (default `true`). `false` leaves out save, edit and delete: all three write, the API asks `permissions.update` for each, and a reader holding only `permissions.read` was offered all three and got a 403 from every one. The expand/collapse switch stays.

## 2.0.0 — 2026-09-16

### Changed

- **Breaking:** `tab` is a `number` — the role id, as the API sends it (from lifeshifter-269).

## 1.2.0 — 2026-09-14

### Fixed

- The switch id went on the component root, so the "Expand all" label toggled nothing; it goes through `inputId` (from lifeshifter).
- Delete stayed clickable while saving: an explicit `:disabled` overrode the one `:loading` implies. It is disabled while `loading` too (from lifeshifter).

### Added

- `MPermissionToolbar.spec.ts` (from lifeshifter), plus the case that delete is disabled while saving.

### Changed

- One filled button: edit and delete are `text`, delete keeping `danger`. The three buttons sit `gap-3` apart — at `gap-1` their touch areas overlapped and aiming at the pencil hit the bin (from lifeshifter).

## 1.1.0 — 2026-06-01

### Added

- `data-testid="save-permissions"` on the save Button.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
