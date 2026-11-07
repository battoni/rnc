---
version: 1.9.0
origin: pendulum
based-on: 1.8.2
---

# RULES — app.rnc/e2e/

> For AI agents. Last updated: 2026-09-22.

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
- Run: `cd app.rnc && npm run test:e2e` or `make e2e`
- Dev server: auto-started via `webServer` (`npx vite --mode test --port $E2E_PORT --strictPort`,
  `E2E_PORT` defaulting to 5173; `baseURL` and the readiness `url` follow it) — no SSL
- **A second checkout (a git worktree) must run with its own `E2E_PORT`**, e.g.
  `E2E_PORT=5175 make pre-commit` from its root. Locally `reuseExistingServer` is on, so with the
  default port the suite silently runs against whatever already answers on 5173 — the OTHER
  checkout's code. And if that server is a plain `npm run dev` (not `--mode test`), the five
  English-route specs fail: the default language `en` comes from `.env.test`, and a dev server
  started from `.env` serves `pt-BR`. The
  Makefile does not set the port on purpose: the main checkout keeps 5173.
- Retries: 1 locally, 2 on CI
- **The browsers are not installed by `npm install`.** A first run on a new machine needs
  `npx playwright install chromium`, or every test fails at browser launch.
- **If port 5173 is already taken** (by the local HTTPS dev server, say), do not fight it: start
  `npx vite --mode test --port 5174` yourself and run with
  `E2E_NO_WEBSERVER=1 E2E_BASE_URL=http://localhost:5174`. The managed `webServer` would otherwise
  try to reuse a server it cannot talk to.
- **Never write a host into a spec.** A page from `browser.newPage()` does NOT inherit the
  project's `baseURL`, so pass it — `browser.newPage({ baseURL: testInfo.project.use.baseURL })` —
  and `goto` a path. A fixed `http://localhost:5173` warms (or tests) whatever else answers there
  when the suite runs against `E2E_BASE_URL`, and a `.catch` hides it.

## Directory layout

```
e2e/
  fixtures/auth.ts   ← shared mock user/role + authenticated page fixture
  auth/              ← login (all modes), sign-up, forgot-password
  users/             ← users CRUD
  roles/             ← roles & permissions
  navigation/        ← the rail, the drawer, the bottom bar, the centred layout, the not-found screen
  theme/             ← what the theme promises, as the browser computed it: contrast, relief,
                       touch targets, scrollbars, the viewport height, the dialog mask
  ideas/             ← the Ideas board and an opened idea, at phone width
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

## Signed-in specs start at `/painel`

`/` is Franco's public site (rnc-1 AM15) and renders no rail; a spec that measures the back office
goes to `/painel`, the `home` route.

## Known Gaps

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

## Do Not

- Do not add `fixed sleep` (`waitForTimeout`) — wait for the event: `waitForLoadState`, `waitFor`,
  `expect.poll` on the value that is moving, or the page's finite animations finishing
  (`document.getAnimations()` → `finished`).
- Do not use `.p-*` class selectors — use semantic ids/roles.
- Do not start a real backend — route-interception covers all flows.
