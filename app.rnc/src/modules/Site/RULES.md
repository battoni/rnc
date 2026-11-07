---
version: 1.1.0
origin: rnc
---

# RULES — Site Module

> For AI agents. rnc-1 F1–F2, 07/11/2026.

Franco Contabilidade's public site (D4): the landing `/`, the six sector pages `/reforma/:slug` and
the CTA's destination `/diagnostico`. No login.

## Intentional Decisions

- **Every route carries `meta: { isOpen: true }`** (plan AM15): it answers a visitor and a signed-in
  member alike. `isPublic` would bounce a signed-in person to the back office; a route with neither
  would send a visitor to the login screen. `routes.spec.ts` guards it.
- **Rendered in `ThePublicLayout`**, never `TheLayout` (which is the back office, with the rail). The
  layout's `<main>` is full width; each section of a view sets its own `max-w-6xl` container, so a
  band (the navy hero) can run edge to edge.
- **The firm comes from the API.** `usePublicFirm` reads `GET /api/public/firm` once per page load
  (shared state; a failure is forgotten so the next page asks again). The header, the footer and
  `useSeo`'s site name all read it. F1's `FIRM_PUBLIC_CONTACT` constant is gone.
- **Sector content comes from the API** (`GET /api/public/sectors`, `…/{slug}`, R6), written by
  `SectorPageSeeder`. The landing's own copy — hero, proof points, "o que muda", the 2026–2033
  transition, the news line — is i18n under `site.landing` (facts.md §1–§2). Both locales carry
  every key; the site is shown in pt-BR, the specs run in English.
- **Tracking goes by beacon, not through `API`** (`recordEvent.service.ts`): the router aborts every
  request in flight on navigation, and a CTA click is a navigation. `navigator.sendBeacon` with a
  `text/plain` JSON body outlives the page and asks no CORS preflight; `fetch` with `keepalive` is
  the fallback. It never throws, and it stays off the app-busy counter.
- **The source is decided once per page load** (`useTracking`): `utm_medium` paid/partner, an ad
  click id → paid, any other tag or an external referrer → organic, otherwise the session's stored
  source or `direto`. It is kept in `sessionStorage` so later pages of the visit carry it. The
  visitor id is a UUID v4 in `localStorage`. Only the derived word leaves the browser (AM2).
- **A visit is counted when the page has content**: the landing on mount, a sector page when the
  API returned it (a 404 counts nothing, so an invented slug never reaches the funnel).
- **`useSeo`** writes title, description and `og:*` at runtime; the firm's name ends the document
  title, the Open Graph title is the page's own. A sector page's SEO is written by the API row.
- **`/diagnostico` is a placeholder** (F2 W12) that hands over Franco's phone and mailbox. F3's
  Diagnostic module takes the path and the route name `site-diagnostic` over.
- **Buttons on the navy band are hand-written** (`bg-primary-300 text-primary-950`): a PrimeVue
  `primary` button is navy on navy there. Navy #000f19 on cyan #14c3ff measures about 9:1;
  white on cyan would not.

- **A site `RouterLink` that can match the current page sets `exact-active-class`** (empty, or
  `text-heading` in the nav): the theme paints `.router-link-exact-active` with the rail's glowing
  bar, which on the site drew a bar beside the logo and every link to `/`.
- **Prose is sans on this theme** (`franco.css`, W13), not battoni-dev's mono.

## Known Gaps

- **No server-side rendering** (D4): crawlers that run no JavaScript see an empty shell.
- **`index.html` and the API still say `noindex`** (the bootstrap's back-office default). The public
  routes must lift it before the site is launched — a deploy decision, out of Phase 1's scope.
- **`lead_token` is never sent yet**: the diagnostic steps (F3) add it to `useTracking`.
