# Changelog — Tip Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.0 — 2026-09-17

### Fixed

- A 422 from the API (tip-001) raised the red "something went wrong" toast: the API sent a translated sentence, `translateError` found no key, and the form showed the generic error. The API now sends `tips.errors.*` keys, which `src/locales` carries. `MAddEditTipForm` shows the refusal under the field it names, with no toast; `TipsView` shows a row-action refusal (unarchive onto a taken place, archive) as a `warn` toast with the translated rule. Specs cover each path, seen failing first.
- An archived row had `statusTone: secondary`, a tone `OListDivided` does not paint, so the Tag fell back to PrimeVue default colours at 1.83:1 (VIS-04). It is `danger`, as an inactive user.
- `MAddEditTipForm` footer buttons `gap-2` → `gap-3` (VIS-15).
- A link inside a tip body (the `TipsView` row detail and `MTipHistoryDialog`) navigated in the same tab and took the person off `/tips`. Both render through `withExternalLinks` (`@Helpers`), which adds `target="_blank" rel="noopener noreferrer"` as a pair (tip-002).
- `MAddEditTipForm`: the dialog's gap filler (`absolute bottom-0 w-full`) had no `left`, so it sat at its static position 20px in and ended 20px past the sheet; on a phone the page grew to 410px, the browser zoomed out, and the dialog covered 370 of 390px with a strip of page on the right. It takes `left-0` (`src/test/dialogGapFiller.spec.ts`).

### Added

- `data-testid="tip-unarchive"` on both unarchive buttons.

## 1.2.5 — 2026-09-17

### Fixed

- `/tips` and `/dicas` declare `meta: { permission: 'tips.read' }`. The navigation only hid the link, so typing the address opened a screen whose every request the API refuses without the same key. `routes.spec.ts` covers both named routes.

### Changed

- `TipsView` and `MAddEditTipForm`: one-line computeds sit in the one-line block (dependencies first), multi-line ones after it. No behaviour change.

## 1.2.4 — 2026-09-16

### Fixed

- The anchor key, the last editor, both form hints and the history timestamp: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.2.3 — 2026-09-15

### Changed

- `TipsView` create CTA moved from `#actions` to `#primaryAction` (still `APageHeaderAction`),
  matching Usuários and Perfis.

## 1.2.2 — 2026-09-15

### Fixed

- `TipsView` `#actions` honours `OListDivided`'s `variant`: History, Archive/Restore and See in
  app are labelled `w-full justify-start` rows in the ⋮ menu. They stayed icon-only beside a
  labelled Edit, so the phone overflow was unreadable.

## 1.2.1 — 2026-09-15

### Fixed

- `TipsView`: `<aside class="contents">` for the overlays. The aside renders nothing in flow, but as a box it is still a flex child of `TheLayout`'s content column, whose `gap-8` reserved 32px under the page for it (from lifeshifter).

## 1.2.0 — 2026-09-09

### Changed

- The "see in app" control is icon-only below `isMobile` and labelled at or above it, rather than icon-only at every width — the label was removed in review even though the spec recorded why it was added.

## 1.1.0 — 2026-09-09

### Changed

- "See in app" on the tips list is an icon-only button with `aria-label` so narrow rows keep a readable title.

## 1.0.0 — 2026-09-04

### Added

- Dicas view: lists every tip with where it is used, filters by text, toggles archived, and flags a
  tip whose anchor no longer exists in the code.
- `MAddEditTipForm` — anchor dropdown limited to free anchors, Quill editor whose toolbar mirrors
  the API allow-list, change reason demanded only when the text actually changed.
- `MTipHistoryDialog` — every version rendered as it looked, with author, timestamp, reason, and
  restore.
- `OTipEditDialog` — the single edit dialog behind every `<ATip>`, mounted by `TheLayout`.
- "See in app" link on every row and in the detail panel, resolved from the anchor's route.
- Services for the full lifecycle: list, active payload, history, create, update, archive,
  unarchive, restore.
