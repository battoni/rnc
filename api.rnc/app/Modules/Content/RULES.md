---
version: 1.1.0
origin: rnc
---
# RULES — Content Module

> For AI agents. rnc-1 F2, 07/11/2026.

## Domain

The public site's editorial content, firm by firm (R6). Today: the six sector pages
(`sector_pages`) — "A Reforma para o comércio" and its siblings — each with a lede, at least three
dated facts and its own SEO.

## Shape

- **`facts`** is a json list of `{date, text, source: {label, url|null}}`. `date` is display text
  ("2026", "01/01/2027", "2029 a 2032"): a statement about a year or a span is not a calendar day.
- **`seo`** is `{title, description}`; the site appends the firm's name to the document title.
- **`position`** is the order the site lists them in; `(firm_id, slug)` is unique.

## Endpoints

- `GET /api/public/sectors` → `PublicSectorPageSummaryResource` (slug, label, title, lede).
- `GET /api/public/sectors/{slug}` → `PublicSectorPageResource` (+ facts, seo), 404 when the slug is
  not the current firm's. Both are allow-lists: no id, no firm.

## Intentional Decisions

- **The content is a default set, not a screen** (`Helpers/DefaultSectorPages`, rnc-4 F3b1): ported
  from the approved demo with each statement's source from facts.md §2; it names no firm.
  `SectorPageService::startFirm($firm)` writes it for one firm — `SectorPageSeeder` (Franco, every
  environment) and `firm:create` (every new firm, R21). The caller runs it as that firm through
  `CurrentFirm::runAs`; `firm_id` is written explicitly, because `DatabaseSeeder` runs without model
  events (so `BelongsToFirm` does not stamp it). Re-running rewrites the text in place.
  *Retired 14/11/2026 (rnc-4 F3b1):* "the content is a seeder" — a new firm needs the same pages
  without running a seeder, so the set moved out of it.
- **Tables read by other modules:** `sector_pages.slug` is read by Tracking's `RecordEventRequest`
  (an event's `sector` must be one of this firm's slugs).

## Known Gaps

- **No editor.** A fact that changes (the CBS delay to 2028, say) is a seeder edit and a re-seed until
  a content screen exists.
