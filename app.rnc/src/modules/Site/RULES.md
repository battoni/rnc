---
version: 1.0.0
origin: rnc
---
# RULES — Site Module

> For AI agents. rnc-1 F1, 07/11/2026.

## Domain

Franco Contabilidade's public site (D4): the landing and, from F2, the six sector pages. No login.

## Intentional Decisions

- **Every route carries `meta: { isOpen: true }`** (plan AM15): it answers a visitor and a signed-in
  member alike. `isPublic` would bounce a signed-in person to the back office; a route with neither
  would send a visitor to the login screen. `routes.spec.ts` guards it.
- **Rendered in `ThePublicLayout`**, never `TheLayout` (which is the back office, with the rail).
- **F1 ships a placeholder landing**: the hero, countdown, proof points, "o que muda" and the timeline
  are F2 (R5), with the content served by the API.

## Known Gaps

- **No server-side rendering** (D4): the pages are a SPA, so crawlers that run no JavaScript see an
  empty shell. Decided for the pilot; F2's `useSeo` sets title, description and Open Graph tags.
