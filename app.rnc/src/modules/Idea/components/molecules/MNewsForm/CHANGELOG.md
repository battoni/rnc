# Changelog — MNewsForm

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.0.1 — 2026-09-17

### Fixed

- The areas `MultiSelect` and the credited-idea `Select` took `id`, which lands on the wrapper; they take `inputId` so each `<label for>` reaches its control (lifeshifter-471). Footer buttons `gap-2` → `gap-3` (VIS-15).

## 2.0.0 — 2026-09-16

### Changed

- **Breaking:** `deliveredIdeaOptions` values and the selected `ideaId` are numbers — idea ids as the API sends them (from lifeshifter-269).

## 1.2.1 — 2026-09-16

### Fixed

- The five field hints and the draft note: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.2.0 — 2026-09-10

### Changed

- The submit button says what the click will do. With the switch on Rascunho it read "Publicar":
  two controls side by side contradicting each other, and the quieter one was the one that decided.

### Added

- `news.actions.saveDraft` in both locales, and `MNewsForm.spec.ts` over the four states.

## 1.1.0 — 2026-09-09

### Changed

- Sticky footer and bottom fade use `bg-canvas` so they match the dialog canvas instead of a floating panel strip.

## 1.0.0 — 2026-09-01

### Added

- First release. Writes one Novidades entry: title, subtitle, body, cover, link, credited idea, and
  the draft/published switch.
- The body is a rich-text `Editor`, not a textarea. Its toolbar is declared through the `#toolbar`
  slot and is deliberately narrower than Quill's default — it offers exactly what
  `NewsBodySanitizer` accepts on the server, so nothing the writer can produce is silently thrown
  away on save.
- The cover uses `MImageUpload`, which brings paste-from-clipboard with it.
