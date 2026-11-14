---
version: 1.5.0
origin: rnc
---

# RULES — Site Module

> For AI agents. rnc-1 F1–F2, 07/11/2026.

Franco Contabilidade's public site (D4): the landing `/` and the six sector pages `/reforma/:slug`.
No login. The CTA's destination `/diagnostico` is the Diagnostic module's (F3); its route name stays
`SITE_ROUTE_NAMES.diagnostic`, exported from here.

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
  the fallback. It never throws, and it stays off the app-busy counter. **On a firm host it goes by
  the keepalive `fetch` straight away** (rnc-4 W10): a beacon cannot carry `X-Firm`, and without it
  the API counts the event on the platform firm. Same-origin since D4, so no preflight.
- **The source is decided once per page load** (`useTracking`): `utm_medium` paid/partner, an ad
  click id → paid, any other tag or an external referrer → organic, otherwise the session's stored
  source or `direto`. It is kept in `sessionStorage` so later pages of the visit carry it.
  `track(type, sector, leadToken)` carries a diagnostic step's lead token, never a lead id;
  `visitContext()` hands the diagnostic's start the same visitor and source. The
  visitor id is a UUID v4 in `localStorage`. Only the derived word leaves the browser (AM2).
- **A visit is counted when the page has content**: the landing on mount, a sector page when the
  API returned it (a 404 counts nothing, so an invented slug never reaches the funnel).
- **`useSeo`** writes title, description and `og:*` at runtime; the firm's name ends the document
  title, the Open Graph title is the page's own. A sector page's SEO is written by the API row.
  _Retired 07/11/2026 (F3):_ "`/diagnostico` is a placeholder" — the Diagnostic module took the path and
  the route name over with the real flow.
- **Buttons on the navy band are hand-written** (`bg-primary-300 text-primary-950`): a PrimeVue
  `primary` button is navy on navy there. Navy #000f19 on cyan #14c3ff measures about 9:1;
  white on cyan would not.

- **A site `RouterLink` that can match the current page sets `exact-active-class`** (empty, or
  `text-heading` in the nav): the theme paints `.router-link-exact-active` with the rail's glowing
  bar, which on the site drew a bar beside the logo and every link to `/`.
- **Prose is sans on this theme** (`franco.css`, W13), not battoni-dev's mono.
- **The motion is the demo's feel, in the repo's grammar** (`src/directives/RULES.md`): what is on
  screen at load enters on mount in CSS (`motion-enter`, `AWordRise`), what is below the fold waits
  for `v-reveal`, and nothing is hidden unless the browser has confirmed it can show it again. The
  words of the headline are split by `AWordRise`, which renders the spaces from script: written in a
  template, the whitespace around `{{ ' ' }}` doubled and the `<h1>` no longer read as its sentence.
  E2e asserts text and counts, never a frame of the motion; the one infinite animation (the hero
  card's float) is ignored by the suite's "finite animations" wait.
- **The hero keeps the countdown in the first screen.** The grid is 1.1fr/0.9fr so the copy column
  holds both buttons on one row at 1440 (at 1.05/1 they wrapped and pushed the countdown 30px under
  a 749px fold); the display headline is semibold — Space Grotesk's 700 at 60px reads as shouting,
  and the demo's weight was 520. A card on the site carries a 1px `ring-line` and a shadow, never a
  coloured left stripe: the stripe is the news line's, and a stripe on a sector card made it read as
  a notice.

- **A sub-brand rides in the address once, then in the tab** (rnc-3 R2, D2): `?marca=<slug>` →
  `sessionStorage` `app.rnc:brand`, read on every ask so whatever page carries `?marca=` wins;
  `?marca=franco` and any 404 `brands.notFound` drop it (the refusal also leaves the address bar, or
  the next read would store the stale slug again). A slug outside `[a-z0-9-]{1,40}` is never sent.
- **Every public call goes through `publicApi`** — never `API` straight — so `X-Brand` is on all of
  them (C1) and a refused brand is retried once as the main brand: a stale tab lands on Franco, not on
  an error. The events beacon cannot carry a header; the API counts it on the main brand (AM21).
- **`usePublicFirm().isFirmMissing`** (rnc-4 R3) is true only after the read answered 404
  `firms.notFound` — the host names a firm the API does not know. `TheRouteGuard` reads it; any
  other failure leaves it false.
- **The brand's colours go on `<html>`, inline** (`useBrandTheme`), not on the layout's element:
  PrimeVue's `--p-primary-*`, the dark scheme's `--color-primary-text` and every teleported overlay
  are declared on the root and resolve there. The ramp is `brandPalette`'s — Franco's shape (500 the
  primary, 300 the accent) with each pair the site paints measured, so any accent the operator picks
  still reads; `e2e/site/brands.spec.ts` measures the painted pairs in both schemes.
- **Under a sub-brand the copy that is Franco's own gives way**: the header and footer mark and the
  copyright carry the brand's name, the footer and the hero kicker its tagline, the proof strip its
  points. The rest of the site's words keep "a Franco" — the sub-brands keep the Franco name (D1).

## Known Gaps

- **No server-side rendering** (D4): crawlers that run no JavaScript see an empty shell.
- **`index.html` and the API still say `noindex`** (the bootstrap's back-office default). The public
  routes must lift it before the site is launched — a deploy decision, out of Phase 1's scope.
- ~~The first page of a brand's tab flashes Franco's kicker and proof~~ — retired by rnc-3 review
  S1 (W116): `usePublicFirm().isBrandPending` is the one flag every brand point waits on unseen —
  both header marks, the footer's mark, tagline and copyright (`opacity-0`), the landing's kicker and
  proof strip (`invisible`, since their entrance animation drives the opacity). A failed answer
  clears it, so the main brand's words show rather than an empty shell.
- **An operator's palette edit shows the old colours for one request** (review S4, W117): the tab's
  `sessionStorage` palette is painted on mount and repainted when `GET public/firm` answers. A stored
  `updated_at` or hash could not skip it — the new one is only known from that same answer, which
  repaints anyway. Only an open tab mid-visit keeps the old firm (and colours) until its next page
  load, as it keeps the old name.
