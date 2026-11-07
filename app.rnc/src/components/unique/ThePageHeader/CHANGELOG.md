# Changelog — ThePageHeader

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.3 — 2026-09-17

### Fixed

- The template comment said the title block was `min-w-0 truncate` and that the title "cuts itself off"; the block is `min-w-0 overflow-hidden` and the title is deliberately never truncated (RULES, "Row 1 never wraps on a phone"). The comment now says what the code does and whose bug a title that does not fit is — the screen's (VIS-19). No behaviour change.

## 1.1.2 — 2026-09-16

### Fixed

- RULES cited `e2e/tracks/mobile.spec.ts`, which does not exist here; they point at `e2e/navigation/navigation.spec.ts` now, and what is not measured (320, 390, full-width mode) is a Known Gap. The header note calling this a downstream synced file whose every decision "will conflict on a `cortex/sync`" was the source project's and is removed — this is the upstream copy.

## 1.1.1 — 2026-09-02

### Fixed

- Whether to collapse the actions is answered on every render instead of by a `computed`. `slots` is
  a plain object and nothing tracks Vue writing into it, so the answer froze at whatever was true on
  the first paint. A page whose actions ARRIVE LATER never got its `⋮` — Novidades hangs a read
  filter here that exists only once the list has loaded, so the filter stayed inline and pushed a
  360px phone out to 421px. The staleness ran the other way on Semana and Temporada, whose actions
  leave while a dialog is open: the `⋮` stayed behind, opening an empty panel over the dialog.

## 1.1.0 — 2026-09-01

### Changed

- Row 1 is `flex-nowrap` below `md`. It wrapped into two lines on a phone, and had been "fixed"
  twice by shaving pixels — each time it came back with a longer title, a different typeface, or a
  layout mode nobody had measured. The actions are `shrink-0` and the title is `whitespace-nowrap`,
  so nothing in the row can drop to a second line. Wide screens still wrap, where a row of many
  actions genuinely wants one.
- The title is one size down on a phone (`text-lg`, `md:text-xl`).

### Removed

- The title's `truncate`. An ellipsis was the first thing tried and it was wrong: a page whose own
  name reads "Personaliza…" has traded a real problem for a worse one. Room comes from the layout's
  padding and the smaller phone size, never from cutting the name.

## 1.0.0 — 2026-09-01

### Added

- `primaryAction` slot — the one action a screen is for, on the title row at every width.
- `collapseActions` prop — opt in to a `⋮` overflow on a phone. Off by default, because a screen
  whose actions include date navigation is made worse by hiding it.

### Changed

- **No longer forces a column below `md`.** A four-letter title and a single button used to end up
  stacked with the width empty beside them. It is a wrapping row now, which stays one line when one
  line fits, and `basis-64` on the title stops the actions crushing it to "T…".

### Notes

- Synced file. This whole unit is a local change and will conflict on `cortex/sync`.
