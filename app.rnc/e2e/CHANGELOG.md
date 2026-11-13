# Changelog — app.rnc/e2e (Playwright)

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.14.0 — 2026-11-07

### Added

- `briefing/briefing.spec.ts` (rnc-3 F9, R26, A23): with the clock fixed at 09:15 São Paulo, `/reinaldo` lands on "Briefing do dia" ("Bom dia, Reinaldo", the KPIs, the actions, the countdown, the panels); each action button lands on its screen — the trail filtered by `?parceiro=` (its request carries `partner_id`), the agenda, the board outlined at `?etapa=proposta` and `?etapa=lead_parcial` (in view at 390), the Radar; 390 and 1440 with no sideways scroll or console error; every word at 4.5:1 in light and dark (colours read through a canvas).

## 1.13.0 — 2026-11-07

### Added

- `contracts/contracts.spec.ts` (rnc-3 F7, R18–R22, A20; R21): Reinaldo sends the most exposed contract to Andrade and the row says "Com Andrade", a client without consent keeps the button disabled with its reason, "Cláusula incluída" asked on the row takes it off the list even below the fold at 390, the import names the lines it left out; the partner's portal shows the contract with its value band; 390 and 1440 with no sideways scroll or console error; the state words at 4.5:1 in light and dark.

## 1.12.0 — 2026-11-07

### Added

- `assistant/assistant.spec.ts` (rnc-3 F5, R12, R13, A14): Patrícia asks the CBS question and reads the reviewed note with its rule, "lida em 05/11/2026" and "revisado por Mariana Toledo em 05/11/2026" at 390 in light and 1440 in dark with no sideways scroll; an unknown question shows the fixed sentence and goes to the specialist she picks; the assistant is in the team's rail.

## 1.11.0 — 2026-11-07

### Added

- `channel/channel.spec.ts` (rnc-3 F3b, R8, R9, A9): the operator advances Litoral and the hero follows the board the move answers (R$ 1.575 → R$ 2.100 a month), the move naming its target; Reinaldo's screen has no button, no "R$ 3.500" and no "pela plataforma" at 390 in light and 1440 in dark, with no sideways scroll; the channel is in his rail.

## 1.10.0 — 2026-11-07

### Added

- `site/brands.spec.ts` (rnc-3 F2, A2, A6): `?marca=express` re-themes the site over Franco's phone, a reload keeps it, `?marca=franco` drops it, a refused brand lands on Franco; the preview banner only on `previa=1`; under Express and Prime, in both schemes, the brand-painted pairs read at 4,5:1 (colours read back through a canvas, since the theme mixes in `oklab`), and nothing scrolls sideways at 390 or 1440.

## 1.9.0 — 2026-11-07

### Added

- `E2E_PORT` (default 5173) sets the port of the managed dev server, its readiness `url` and
  `baseURL`, and the server starts with `--strictPort`. A second checkout of the repo on the same
  machine ran its suite against the first checkout's server on 5173, because `reuseExistingServer`
  takes whatever answers there.

## 1.8.2 — 2026-09-22

### Fixed

- `touch-chrome.spec.ts`: `expectApart` groups controls by ROW before checking the 12px between
  neighbours, and the drawer case waits for the entrance to settle. Both came back from
  new.impressao, where the check failed on a correct layout: its pager wraps at 390px because the
  Portuguese page report is a longer sentence than the English one, and the helper then measured
  the horizontal distance between a button on line 1 and one on line 2 as a 4px gap. The drawer's
  rows measured 41px of a 44px box while the sheet was still sliding in.

## 1.8.1 — 2026-09-22

### Fixed

- `theme/contrast.spec.ts` "the active theme defines what reads on a filled brand surface" asks the stylesheets whether a rule other than `tailwindcss-primeui`'s declares `--color-primary-contrast`, instead of comparing the resolved value with `--color-ink-900`. The old assertion encoded battoni-dev's answer (dark ink on lime) and failed every brand whose fill takes white — chapaja's Terra and lifeshifter's terracotta, both blocked in phase 2 with the token correctly declared. Red with the declaration removed from `battoni-dev.css`, green with it set to `#ffffff`; the 4.5:1 test beside it still guards the value.
- `fixtures/auth.ts`: `seedAuthSession` registers a catch-all `**/api/**` that aborts with `connectionrefused` before any spec mock, so an unmocked call fails the same way locally as in CI. From lifeshifter's T0, where a running local API answered the Tips, Tracks and permission-group calls with 401 and the app signed the spec out mid-test. Preventive here: rnc's 98 specs pass with and without it, and this machine's browser cannot reach the local API at all, so the 401 does not reproduce.

## 1.8.0 — 2026-09-17

### Added

- `theme/contrast.spec.ts`: every `celer-tag-*` helper and the default Tag measured on their own fills, light and dark. It failed on `celer-tag-soft-primary` (1.83:1) before the fix.
- `theme/touch-target.spec.ts`: the default-size buttons, selects and multiselects on /components clear 44px once the growth is counted (the border is subtracted from the computed inset), and a dialog close-button probe is reached at least a pixel past its edge and at 44px, walked out by a thumb in half-pixel steps; the drawn-size case also checks a default button. Both new cases failed (42, and 40–41px) before the growth was extended (VIS-18).
- `theme/dialog-mask.spec.ts`: "the dialog on a touch phone" — with `isMobile`, the page, the mask and the sheet measure 390 from 0. It failed at `[0, 370]` while a form's gap filler ran 20px past the sheet.

## 1.7.0 — 2026-09-17

### Added

- `theme/touch-chrome.spec.ts`: the shared controls under a finger at 390 (and the list actions at 800) — the bottom bar reached 4px past every edge with no two hit areas overlapping, the page-header action, the pager, the drawer's child rows, the drawer's and the sheet's close buttons, and the list row actions at 44px and 12px apart. Each case failed against the code before its fix.
- `theme/contrast-controls.spec.ts`: the SelectButton / ToggleButton option, the secondary text button and the `rounded` default Tag, in both schemes, translucent grounds composited over the canvas. It failed on eleven pairs before the preset fix.

### Changed

- `theme/contrast.spec.ts`: the mobile drawer case is green — its fix (`ONavbarDrawer`, `bg-canvas`) landed. The spec itself is unchanged.

## 1.6.0 — 2026-09-17

### Added

- `theme/contrast.spec.ts` measures every filled button severity (primary, secondary, success, info, warn, danger, help, contrast) at rest, hover and active in both schemes; the brand ramp where the preset writes words with it (outlined, text and link buttons, the highlighted option); the rendered badges and active tab; the `celer-button-*` helpers on a real button in every state; the float label holding a value and focused; and the mobile drawer as a ground for `muted`/`subtle`. Each failed against the code before its fix (the drawer still fails — its fix belongs to the drawer).
- `theme/scrollbar.spec.ts`: the computed `scrollbar-color` is thumb + canvas track, and `.scrollbar-brand` exists.
- `theme/viewport-height.spec.ts`: `#app` follows the visible viewport, not the html/body chain.
- `theme/touch-target.spec.ts`: an icon-only small button is hit 4px outside its edge and not 8px outside, and it is drawn at the same size under a finger and under a mouse (it was 32×28 against 32×35).

### Fixed

- `auth/login.spec.ts` warmed `http://localhost:5173` whatever `E2E_BASE_URL` said; it passes the project's `baseURL` to the page it opens.
- `navigation/navigation.spec.ts` and `theme/dialog-mask.spec.ts` slept (`waitForTimeout(600/700)`); they poll the moving width and wait for the page's finite animations to finish.

## 1.5.0 — 2026-09-16

### Added

- `theme/contrast.spec.ts` measures `--color-muted` (4.5:1) and `--color-subtle` (3:1) — and PrimeVue's muted text, placeholder, float label (4.5:1) and field icon (3:1) — on canvas, panel and panel-muted in both schemes. Red against the old light values: muted 4.09:1 and subtle 1.90:1 on the canvas, Aura's placeholder 3.43:1; red against a dark muted of `#565859` (2.77:1).

## 1.4.1 — 2026-09-16

### Changed

- `fixtures/auth.ts` `mockRole.id` and the id fields of the board and news overflow fixtures are integers, the shape the API sends (from lifeshifter-269).

### Changed

- `mockUser` carries `permissions.read` and `permissions.update`: the roles screen closes its route on `permissions.read` now.

### Added

- `roles.spec.ts`: an account without `permissions.read` that types the address lands on Home (lifeshifter-459).

## 1.4.0 — 2026-09-16

### Added

- `seedLanguage(page, locale)` in `fixtures/auth.ts`, and the three narrow-screen specs (`users-narrow`, `ideas/board-overflow`, `news/news-overflow`) now run in pt-BR. `users-narrow` asserts the Portuguese labels only, so it goes red if the seed stops working — confirmed by removing it (from lifeshifter, in the form the owner chose: the suite stays in English).

## 1.3.2 — 2026-09-15

### Changed

- `navigation/not-found.spec.ts`: comment clarifies that named `push` (not dropping App.vue
  out-in) is what must keep Home visible after "Back to start".

## 1.3.1 — 2026-09-15

### Added

- `navigation/not-found.spec.ts`: signed-in person clicks "Back to start" and lands on a visible
  Home without reload — catches the blank content column after the out-in route fade.

## 1.3.0 — 2026-09-14

### Added

- `ideas/board-overflow.spec.ts`: at 390px, an idea whose title, description, maintainer answer and
  comment are one 300-character run with no break opportunity stays inside its box — on the board
  and in the opened idea — and the page never scrolls sideways (from lifeshifter). The description
  on the card measured 2209px over its own box before the fix; the document assertion alone passed
  with the bug in place, which is why both are asserted.
- `navigation/not-found.spec.ts`: a mistyped address shows the not-found screen to a signed-out
  visitor AND to a signed-in person, on a hard navigation — the path `TheRouteGuard` owns.
- `navigation/layout-centring.spec.ts`: the rail + content ensemble is centred at 1440, 1920 and
  2560, with the rail present and collapsed — the outcome, not either of the two widths that
  produce it (from lifeshifter).
- RULES: the browsers are installed separately; what to do when 5173 is taken; `data-testid` is the
  preferred selector; the mock user carries the permission its route demands (from lifeshifter).

### Changed

- `navigation/navigation.spec.ts`: the administrator mock holds `design.read`. The Design group is
  gated on that permission instead of the role slug, so a mock that only carried the slug lost the
  group — the same place the seeded administrator gets it from `RoleSeeder`.

### Fixed

- `roles/roles.spec.ts` asserts the save button and the new-profile button instead of wrapping both in `if (isVisible)` — a renamed button made both tests pass without testing anything. The new-profile button is found by `data-testid="add-profile"` (from lifeshifter).

## 1.2.0 — 2026-09-12

### Added

- `static/robots.spec.ts`: `/robots.txt` is served at the root, disallows every path, names the
  AI operators individually, and carries `Content-Signal` in EVERY user-agent group. New
  directory, and the first thing here that asserts what the app serves rather than what a person
  does — see RULES.md for why reachability needs a request rather than a file read.

### Fixed

- Two assertions in that spec passed against a missing file before a mutation run caught them.
  With no `robots.txt`, the dev server answers `/robots.txt` with the SPA's `index.html` — status
  200 and no directives — so `not.toMatch(/^Disallow:\s*$/)` was satisfied by a page that is not
  robots.txt at all, and `signals.length === groups` was satisfied by `0 === 0`. Both now anchor
  on a positive assertion first. The same flaw was in api.rnc's `IndexingTest` and is fixed there.

## 1.1.1 — 2026-09-10

### Fixed

- `news-overflow.spec.ts` mocks the list endpoint without naming `/api`. CI has no `.env`, so
  `VITE_API_URL` is empty there and the call goes to `/news` — the pattern passed on every machine
  and failed on the only one that matters. With the prefix gone, the API's list and the page's own
  URL are the same path, so the document request is now let through by hand.

## 1.1.0 — 2026-09-10

### Added

- `news/news-overflow.spec.ts`: a headline with no break opportunity stays inside its card, its
  article and the phone-width screen. It has to be e2e and it has to measure the element against
  itself — the grid tracks are fixed, so no box grows and no document scrolls, and every layout
  assertion anyone reaches for first passes with the defect in place.

## 1.0.0 — 2026-05-31

### Added

- Initial Playwright e2e suite with route-interception (no real backend needed).
- auth/login.spec.ts: login form renders, username mode with mocked POST, validation, /entrar alias, /login-email.
- auth/signup.spec.ts: sign-up page renders with mocked auth/me.
- auth/forgot-password.spec.ts: forgot-password + support pages render.
- users/users.spec.ts: list loads from mocked GET, add dialog opens, create/archive routes wired.
- roles/roles.spec.ts: roles load, permission groups display, save PATCH verified.
- fixtures/auth.ts: shared mockUser/mockRole fixtures and authenticated page extension.
