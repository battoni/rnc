---
version: 1.22.0
origin: pendulum
based-on: 1.8.2
---

# RULES — app.rnc/e2e/

> For AI agents. Last updated: 2026-11-13.

## Purpose

Playwright end-to-end tests for all shipped app.rnc flows. Tests use route-interception
(`page.route`) so no real api.rnc backend is required.

`static/` is the one exception to "flows": it asserts what the app SERVES rather than what a
person does. It lives here because this is app.rnc's only gate that speaks HTTP, and for a file
like `robots.txt` the property worth proving is reachability, not existence — codelumen's edge
gate matches `/:path*`, so a `robots.txt` placed there is answered with an OAuth redirect and
states nothing to anyone. Reading the file off disk cannot tell that apart from a served file.
A request can.

## Stack

- Playwright (Chromium) — `playwright.config.ts` at app.rnc root
- Run: `rtk make e2e` (one file: `rtk make e2e E2E_ARGS=<spec file>`). A bare `npx playwright test`
  with no `E2E_PORT` and no `CI` fails at config load, naming `make e2e`.
- Dev server: always its own, started via `webServer` (`npx vite --mode test --port <E2E_PORT>
  --strictPort`) — no SSL. CI keeps the fixed 5173.
- Retries: 1 locally, 2 on CI. **Locally a test that passed only on its retry fails the run**
  (`failOnFlakyTests`): fix the flake, never rerun until green.
- Reporter: `line` plus `html` with `open: 'never'`, so every run exits on its own; browse the
  report with `npx playwright show-report`.
- **The browsers are not installed by `npm install`.** A first run on a new machine needs
  `npx playwright install chromium`, or every test fails at browser launch.
- **`make e2e` picks the port; a taken port fails.** Each checkout has its own e2e port
  (`python3 scripts/worktree.py port`; an exported `E2E_PORT` wins), and the suite never reuses a
  server already on it (`reuseExistingServer: false`): whatever answers there is not this checkout's
  app, and a plain `npm run dev` would serve `pt-BR` where the specs expect the `.env.test`
  language. Stop what holds the port instead of pointing the suite somewhere else.

  _Retired:_ "A second checkout must run with its own `E2E_PORT`, and if 5173 is taken start Vite on
  5174 and switch the managed server off (`E2E_NO_WEBSERVER`, `E2E_BASE_URL`)". It was the
  workaround for a shared port, and reusing a running server let a suite pass against another
  checkout's code; the overrides are gone from the config and `make e2e` now picks the port.
- **Never write a host into a spec.** A page from `browser.newPage()` does NOT inherit the
  project's `baseURL`, so pass it — `browser.newPage({ baseURL: testInfo.project.use.baseURL })` —
  and `goto` a path. A fixed `http://localhost:5173` warms (or tests) whatever else answers there
  when the suite runs on another port, and a `.catch` hides it.
- **The suite runs on Franco's host** (`http://franco.localhost:<port>`, rnc-4 D31), so every call
  names `X-Firm: franco`. A spec about another host (the platform root, an unknown subdomain) swaps
  only the hostname of the project's `baseURL` (`new URL(path, baseURL)` then `url.hostname = …`),
  never writes a port — `tenancy/firm-host.spec.ts` is the pattern.

## Directory layout

```
e2e/
  fixtures/auth.ts   ← shared mock user/role + authenticated page fixture
  auth/              ← login (all modes), sign-up, forgot-password
  users/             ← users CRUD
  roles/             ← roles & permissions
  tenancy/           ← the firm comes from the host: X-Firm, firms.mismatch, unknown subdomain (rnc-4)
  navigation/        ← the rail, the drawer, the bottom bar, the centred layout, the not-found screen
  theme/             ← what the theme promises, as the browser computed it: contrast, relief,
                       touch targets, scrollbars, the viewport height, the dialog mask
  ideas/             ← the Ideas board and an opened idea, at phone width
  channel/           ← the RNC channel: the operator's advance, Reinaldo's read-only screen (rnc-3)
  assistant/         ← the Reforma assistant: an answer with its source, the hand-off (rnc-3)
  contracts/         ← the contract alert: legal referral, consent, clause, import; the portal's band (rnc-3)
  briefing/          ← Reinaldo's daily briefing: the landing, every action's screen, both schemes (rnc-3)
  static/            ← what the app serves, not what a person does (robots.txt)
```

## Route interception pattern

```typescript
await page.route('**/auth/login**', (route) =>
  route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
);
```

Mock auth/me in beforeEach for protected routes — TheRouteGuard calls it on hard navigation.

## A narrow screen is measured in Portuguese (CRITICAL)

The suite runs in English (`.env.test` pins `VITE_DEFAULT_LANGUAGE=en`, matching the local `.env`).
**A spec that measures a narrow screen does not**: it calls `seedLanguage(page, 'pt-BR')` before
`page.goto`. Portuguese labels are much longer — "Planejamento" needs 124px where "Planning" needs
90 — and lifeshifter shipped three clipping bugs that its English suite could not see and a real
phone showed at once.

The whole suite was not switched, on purpose: every spec that asserts English text would have to
change, for a gain that only the measuring specs collect. A spec that seeds pt-BR asserts the
Portuguese text, not an alternation — an alternation passes whether or not the seed worked.

## Selectors

Prefer `data-testid` (`save-permissions`, `add-profile`, `idea-card-1`) — it survives copy changes
and translation. IDs set by PrimeVue props (`#loginPassword` via `inputId`) and
`getByRole`/`getByText` are fine. Never CSS classes — they change with PrimeVue theme upgrades.

## The mock user must carry the permission the route demands

`mockUser.permissions` is not decoration: the route guards read it against the route's
`meta.permission` and **redirect to home when it is missing**, so the spec then asserts against a
page that never rendered and fails on a locator rather than on a permission. `/users` needs
`users.read`, the roles screen `permissions.read`, the workspaces `leads.read` / `metrics.read` / `users.read`. When a route gains a `meta.permission`, grant it
in the fixtures in the same change.

**And the firm must have the module the screen belongs to** (rnc-4 F3c, R17): a route carrying
`meta.module` is "not found" while the session's `firm.modules` lacks it, and its rail entry is gone.
`mockUser.firm` has every opt-in module on (as Franco is seeded); a spec about a module being off
overrides `firm` (`tenancy/modules.spec.ts`). A public page's firm (`public/firm`) lists `modules`
too — the chat toggle needs `chat` (`diagnostic/chat.spec.ts`).

## Signed-in specs start at `/painel`

`/` is Franco's public site (rnc-1 AM15) and renders no rail; a spec that measures the back office
goes to `/painel`, the `home` route.

## Known Gaps

- **A cold first run can flake `touch-target.spec.ts` "an icon-only small button is hit 4px outside"**
  (seen once, 13/11/2026, on the first `make e2e` of a fresh worktree with no Vite cache: it failed at
  line 73, passed on retry, and `failOnFlakyTests` failed the run with exit 2; the rerun was 7 of 7
  green). Not diagnosed: cold compile of the personalization route is the suspect. Rerun once before
  hunting; if it recurs warm, find the wait it lacks.
- **No component gallery to measure** (rnc-1 F1). Four cases measured the Showcase's `/components`
  page — list row actions under a finger, default-size controls at 44px, the rendered badges and
  active tab, and the toggle/secondary/tag contrast (`contrast-controls.spec.ts`). They left with the
  Showcase module. Their rules still hold through the preset, and the tokens-only contrast cases
  remain; each comes back as a screen of the hub renders those controls (F5 kanban, F8 repricing).

## Measuring overflow

Assert `<= 0`, never `toBe(0)`: with `scrollbar-gutter: stable` the gutter belongs to the client
box, so a page that does not scroll sideways measures NEGATIVE. And measure the element against
itself (`scrollWidth - clientWidth` on the text's own box) as well as the document — a fixed grid
track keeps the document still while unbreakable text paints straight across its neighbour.

## Measuring colour

Measure a colour with transitions switched off (`addStyleTag` with `transition: none`): switching
`data-theme` and hovering both start a transition, and a colour read mid-way is neither the old one
nor the new one — a dark tab measured 4.14:1 on its way to 17. And measure what RENDERS where a
stylesheet can outvote a token: a global rule once repainted every badge whatever its token said.

## Measuring text

A size that depends on text waits for its font: force layout on the element (so its face is
requested), then `await document.fonts.ready`, then measure. The theme's fonts load with
`font-display: swap`, so a label measured too early is in the fallback face — "Sign in" read 76px
against 74.8 in Figtree, and `touch-target.spec.ts` failed 2 runs in 100 comparing two contexts that
each raced it (boanerges-14 F4). A page's own `waitFor` on a button does not cover it: the font a
probe injected afterwards needs may not have been requested yet.

## Do Not

- Do not add `fixed sleep` (`waitForTimeout`) — wait for the event: `waitForLoadState`, `waitFor`,
  `expect.poll` on the value that is moving, or the page's finite animations finishing
  (`document.getAnimations()` → `finished`).
- Do not use `.p-*` class selectors — use semantic ids/roles.
- Do not start a real backend — route-interception covers all flows.
