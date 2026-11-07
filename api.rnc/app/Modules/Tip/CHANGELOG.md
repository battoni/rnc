# Changelog — Tip Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.0 — 2026-09-17

### Changed

- The three `TipService` refusals answer i18n keys — `tips.errors.anchorTaken`, `tips.errors.reasonRequired`, `tips.errors.contentRequired` — instead of `__()` sentences translated by the request's locale, which the SPA could not look up and showed as the generic error toast. `lang/en/tips.php` and `lang/pt_BR/tips.php` are removed; the sentences belong in the SPA's locales. `TipRefusalKeysTest` (from new-impressao tip-001, review ARC-13).

## 1.0.1 — 2026-09-17

### Fixed

- `tips.content` and `tip_histories.content` are `mediumText`: the request accepts 20,000 characters, which can pass TEXT's 65,535 bytes on MySQL. `NewsColumnCapacityTest` compiles both create migrations with the MySQL grammar.

## 1.0.0 — 2026-09-04

### Added

- Editable contextual help: `tips` + `tip_histories`, anchored to `<ATip>` call sites declared in
  the frontend's anchor catalogue.
- `TipContentSanitizer` (symfony/html-sanitizer) — allow-list applied on write, no `<img>`,
  http(s) links only, and Quill's non-breaking spaces collapsed so tip text wraps.
- Append-only event log with `event`, `change_reason`, `changed_by` and `restored_from_id`;
  restore appends a new version rather than rewinding.
- Archiving instead of deletion; archiving frees the anchor and is itself a logged event.
- `GET /api/tips/active` open to any authenticated user; management routes behind `tips.read` /
  `tips.manage`.
- `TipSeeder` migrating the 16 tips that shipped hard-coded in the frontend locale files.
