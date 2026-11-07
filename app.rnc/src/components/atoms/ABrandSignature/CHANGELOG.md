# Changelog — ABrandSignature

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.2 — 2026-09-16

### Fixed

- The label above the wordmark: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).
- The label was 10px (`text-[0.625rem]`), under the 12px floor (`celer-06`); it is `text-xs`.

## 1.0.1 — 2026-09-14

### Fixed

- The battoni.dev mark is painted from `styles/components/attribution.css`, pinned to the studio's own ink and lime, instead of `text-ink-900 dark:text-primary-500` — which, in any project with another brand, repainted the studio's mark in that brand's colour (from lifeshifter).

## 1.0.0 — 2026-06-13

### Added

- Authorship signature lockup (monogram + monospaced caption) + unit tests.
