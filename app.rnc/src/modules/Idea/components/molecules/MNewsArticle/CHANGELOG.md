# Changelog — MNewsArticle

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.4 — 2026-09-17

### Changed

- The body's links take `target="_blank" rel="noopener noreferrer"` through `withExternalLinks` (`@Helpers`, tip-002) instead of a DOM pass after mount — one mechanism for the rule `ATip` and the Tip module already follow. The behaviour was already right; the new spec case pins it (it fails without either mechanism).

## 1.3.3 — 2026-09-17

### Fixed

- The entry link and the body's links were `primary-700`, 1.93:1 on the light canvas — the primary ramp does not flip. Both take the heading role and an underline (CEL-01).

## 1.3.2 — 2026-09-16

### Fixed

- The date line and the credit: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.3.1 — 2026-09-10

### Fixed

- Headline and standfirst wrap (`wrap-anywhere`). The body's stylesheet already carried
  `overflow-wrap: anywhere` for exactly this; the rule was written for the body and never reached
  the two lines above it.

## 1.3.0 — 2026-09-09

### Changed

- Optional `reactionsDisabled` disables applause while the view cools down after a 429.

## 1.2.0 — 2026-09-02

### Changed

- The body reads at `text-base leading-relaxed`. At `text-sm` it was set at the size of an interface
  label, and this is the one place in the app that holds running prose somebody is meant to finish.
- The body's own `h1`–`h3` grew from `1.05rem` to `1.2rem`, keeping the ratio they had against the
  smaller body. Left alone they would have been 1.05× the paragraphs around them — a heading told
  apart only by its weight.

## 1.1.0 — 2026-09-01

### Changed

- The entry's title is an `h2`. `ThePageHeader` renders the page's only `h1`, and this article
  opens on top of that page.
- The applause carries `data-testid="news-article-reactions"`. The same buttons now sit on every
  card, so an unscoped `news-react-clap` matches four elements and a spec cannot address the one
  in the article.

### Fixed

- A cover that fails to load drops its box instead of rendering `alt` — the entry's title — as bare
  text above the article.

## 1.0.0 — 2026-09-01

### Added

- First release. Renders one Novidades entry in full — cover, date, title, subtitle, body and link.
