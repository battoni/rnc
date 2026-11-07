# State: Franco Reforma Hub — the real pilot
Stage: build F2/8
Updated: 07/11/2026
## Done
Intent approved 07/11 · spec closed 07/11 (26 R · 15 A · 9 D) · plan approved 07/11 (F1–F8) + plan-review amendments AM1–AM15. Bootstrap configured as rnc (9069414).
F1 Foundation (R1–R4, A1, A2): Idea/Tip/Showcase + Home sample removed with their full test surface (API modules, migrations `tips*`/`ideas*`/`idea_*`/`news*`, factories, seeders, routes, limiters, SPA modules, aliases, locale keys, `ATip`/tip store/tutor toggle, features catalogue, e2e specs); `Firm` module (`firms`, `CurrentFirm`, `ResolveCurrentFirm`, `FirmScope`, `BelongsToFirm`, `FirmSeeder`); roles `operador`/`ceo`/`equipe` with the AM8 keys, `users.firm_id` + `is_specialist`, `LocalUsersSeeder`; SPA theme `franco`, `ThePublicLayout`, text `ALogo`, `Site` module (open `/`), back office `home` at `/painel` + three workspaces and the permission-filtered rail; local env (.env files, `Makefile.local`); `CLAUDE.md` + `project-context.mdc` describe the product. Suites: 345 Pest · 747 Vitest · 85 Playwright, all green; A1 proven red with the scope disabled (7 of 10 failed). pre-commit green 07/11/2026.
F2 Site (R5–R8, A3): API `Content` module (`sector_pages`, `SectorPageSeeder` with the six sectors and dated, sourced facts, `GET /api/public/sectors[/{slug}]`), `GET /api/public/firm` in the `Firm` module, `Tracking` module (`visitors`, `events`, `POST /api/public/events` per AM2, throttled 60/min, beacon body); SPA `Site`: landing (hero, CBS countdown, proof points, o que muda, 2026–2033 timeline, news line, sector grid, closing CTA), `/reforma/:slug`, `/diagnostico` placeholder (W12), `usePublicFirm`, `useTracking`, `useSeo`; `ThePublicLayout` reads the firm from the API (F1's `FIRM_PUBLIC_CONTACT` removed). Suites: 383 Pest · 778 Vitest · 99 Playwright, all green; new tests seen red first (API) or by mutation (SPA). Verified against the real API on a scratch SQLite file: seed runs, landing + `/reforma/saude` + `/diagnostico` load in headless Chromium at 390 and 1440 px with no console error and no sideways scroll, beacons stored (source carried across pages, CTA survives navigation). pre-commit green 07/11/2026.
## Next
`/battoni-start-session rnc-1` → F3 Diagnostic and result (R9–R14). F3 takes over `/diagnostico` (route `site-diagnostic`) from the placeholder and wires `lead_token` in `TrackingService::record`. MySQL is still unverified locally — see W1.
## Local environment
- URLs: API `http://localhost:8000` · SPA `http://localhost:5173` (one host, `localhost`; never `127.0.0.1` beside it — AM1). The site is `/`, the back office `/painel` (login at `/login`).
- Serve: `make serve-api` (`php artisan serve --host=localhost --port=8000`) and `make serve-app` (`npm run dev`, Node 24: `source ~/.nvm/nvm.sh && nvm use 24`). Rebuild + seed: `make fresh-seed` (`php artisan migrate:fresh --seed`).
- Database: MySQL 8 at `127.0.0.1:3306`, database `rnc`, user `root`, `DB_PASSWORD` empty in `api.rnc/.env` — but this machine's root refused an empty password (W1); set `DB_PASSWORD` in `api.rnc/.env` and create the database (`CREATE DATABASE rnc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`). Pest runs on in-memory SQLite.
- `.env` (both gitignored) as AM1: `APP_URL=http://localhost:8000`, `FRONTEND_URL=http://localhost:5173`, `CORS_ALLOWED_ORIGINS=http://localhost:5173`, `SESSION_DOMAIN=null`, `SESSION_SAME_SITE=lax`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`; SPA `VITE_API_URL=http://localhost:8000/api` (W11), `VITE_ACTIVE_THEME=franco`, `VITE_LOGIN_FLOW=username`, SSL vars empty (plain http).
- Seeded people (login = username + password, all firm `franco`):
  | Username | Password | Role | Specialist |
  | --- | --- | --- | --- |
  | `battoni` (Guilherme Battoni) | `franco-operador` (`SUPERADMIN_PASSWORD`) | operador | no |
  | `reinaldo` (Reinaldo Franco) | `franco-local` (`SEEDER_PWD`) | ceo | no |
  | `patricia` (Patrícia Gomes) | `franco-local` | equipe | no |
  | `mariana.toledo` | `franco-local` | equipe | yes |
  | `carlos.prado` | `franco-local` | equipe | yes |
  | `juliana.reis` | `franco-local` | equipe | yes |
  | `rafael.antunes` | `franco-local` | equipe | yes |
  On a production database only `battoni` is seeded (`LocalUsersSeeder` refuses to run there).
## Pitfalls
- Node 24 (`.nvmrc`); the shell's nvm default is 20 — run `source ~/.nvm/nvm.sh && nvm use` first.
- Valet is broken in non-interactive shells (`_valet_run` not found): serve the API with `php artisan serve`.
- Pest runs on in-memory SQLite; the app on MySQL 8 — migrations must work on both.
- A throwaway database for a run: `DB_CONNECTION=sqlite DB_DATABASE=/abs/path.sqlite php artisan …` (shell variables win over `.env`, `serve` included).
- The login throttle (`throttle:login`) answers 429 after a few scripted attempts; `php artisan cache:clear` resets it locally.
- `BelongsToFirm` is unused until F3's `Lead`: delete the `trait.unused` entry in `api.rnc/phpstan.neon.dist` then.
- Signed-in e2e specs start at `/painel`; `/` is the public site and has no rail.
- `DatabaseSeeder` runs WITHOUT model events: `BelongsToFirm` does not stamp `firm_id` in a seeder, and `FirmScope` hides every row unless the seeder runs inside `CurrentFirm::runAs` (see `SectorPageSeeder`).
- The router aborts every request in flight on navigation, so an event sent through `API` right before a `push` is lost — tracking uses `navigator.sendBeacon` (`recordEvent.service.ts`).
- The theme paints `.router-link-exact-active` with the rail's glowing bar; a site `RouterLink` that can match the current page sets `exact-active-class`.
- `ALogo`'s own `inline-flex` outvotes a `hidden` passed to it: wrap it in a span to hide it responsively.
- The public shell clips sideways overflow (`overflow-x-clip`), so a too-wide header shows as a cut button, not a scroll — `e2e/site` measures the header CTA against the viewport.
- Vitest has no component auto-import: a view spec registers the module's molecules and PrimeVue's `Button`/`Skeleton` in `global.components`.
- A rtk-filtered `make pre-commit` log drops the suite counts; run the suites directly to count them.
## Away run
Stop at: PR after verify · Reviewer: fable-reviewer (#fable session) · Chrome QA: on
Started: 2026-11-07
## Decided while you were away
00:07 F1 started, attempt 1
- W1 F1: MySQL `root` without a password was refused on this machine (`ERROR 1045 root@localhost`), so `migrate:fresh --seed` could not run on MySQL → verified the migrations, seed and the four role logins on a SQLite file against the real API (`php artisan serve` + curl), left `.env` on MySQL `rnc`/root/empty as briefed (credential lookup was not attempted; migrations use no MySQL-only SQL, AM11; to reverse: set `DB_PASSWORD`, create `rnc`, run `make fresh-seed`).
- W2 F1: four e2e cases measured the Showcase's `/components` gallery (list row actions, default-size controls, badges/active tab, `contrast-controls.spec.ts`) → removed with the Showcase; the brand-text contrast case now reads the preset on `/painel` (the gallery was R4's to delete; to reverse: bring them back on the first hub screen that renders those controls — Known Gap in `e2e/RULES.md`).
- W3 F1: where the back office lands now that `/` is the public site → `home` moves to `/painel`, keeping its route name (every guard pushes `{ name: 'home' }`; to reverse: change the path in `Home/views/Home/routes.ts`). Spec C1.
- W4 F1: AM15 says `meta: { public: true }` but the bootstrap's guards read `isPublic` (signed-out only, bounces a signed-in person) and `isOpen` (everyone) → the site routes use `meta: { isOpen: true }` (it is AM15's intent; `public` would be ignored by both guards; to reverse: none needed).
- W5 F1: the bootstrap's `manager`/`user` roles, `ManagerSeeder` and the `*-exclusions.local.php` seams → removed; `RoleSeeder` writes the three roles key by key and `RoleService::ADMINISTRATOR_SLUG` is `operador` (the protected all-keys role; to reverse: restore from 9069414).
- W6 F1: the brand mark → a text `ALogo` ("Franco Contabilidade", "F" tile), the bootstrap PNGs deleted, the sign-in aside shows it instead of the bootstrap author's mark; the Personalization "contextual tips" toggle left with the Tip module (to reverse: an SVG mark in `ALogo`).
- W7 F1: Vite's dev server required Valet certificates → https only when `VITE_LOCAL_SSL_KEY`/`CERT` are set, plain http otherwise (AM1 asks for `http://localhost:5173`; to reverse: set the two vars).
- W8 F1: the features-catalogue check (`build-features.mjs`) generated a page of the Showcase → removed from `make check-app`, CI and `package.json` (to reverse: restore the script with a new destination).
- W9 F1: where the operator reaches users and roles → the Operador rail group carries Usuários and Perfis as children (they also stay in the settings footer; to reverse: drop `OPERATOR_CHILDREN` in `navItems.local.ts`).
- W10 F1: Franco's public contact is needed by the footer before F2's `GET /api/public/firm` exists → a constant `FIRM_PUBLIC_CONTACT` in `@Constants`, to be replaced in F2 (to reverse: F2 does).
- W11 F1: AM1 lists `VITE_API_URL=http://localhost:8000`, but the SPA appends endpoints to it without `/api` (the browser's login went to `/auth/login` and failed CORS) → `VITE_API_URL=http://localhost:8000/api` (verified: the four seeded people sign in through the SPA in Chromium against the real API, each lands on `/painel` with its rail — operador 3 groups, ceo 2, equipe 1 — and no console error; to reverse: none, it is the bootstrap's contract).
00:53 F1 done, attempt 1, cd32dd4
00:54 F2 started, attempt 1
- W12 F2: every CTA needs a destination before F3's diagnostic exists → a placeholder `/diagnostico` (route `site-diagnostic`, open) that says the diagnostic opens soon and hands over Franco's phone and mailbox from the API; CTA clicks are tracked as `cta` (to reverse: F3 replaces the view and keeps the route name). Spec C2.
- W13 F2: the `franco` theme inherited battoni-dev's mono "technical voice" for every paragraph, which is Battoni Dev's tell, not Franco's → `franco.css` sets prose back to the sans face (back office included; to reverse: delete the block at the end of `franco.css`).
- W14 F2: an event sent through `API` before a navigation is aborted by the router (a CTA click is a navigation) → the site sends events with `navigator.sendBeacon` as a `text/plain` JSON body (no CORS preflight), `fetch` keepalive as fallback, and `RecordEventRequest` reads a plain body that is JSON (to reverse: send through `API` and accept lost CTA events).
- W15 F2: how utm/referrer map to the four sources → `utm_medium` in a paid list (cpc, ppc, paid, display…) or an ad click id (gclid, fbclid, msclkid) = `pago`; `utm_medium` parceiro/partner/affiliate = `parceiro`; any other tag or an external referrer = `organico`; nothing = the session's stored source, else `direto`; the visitor row keeps its first touch (to reverse: `deriveTrafficOrigin` in `useTracking.ts`). Spec C3.
- W16 F2: the plan lists `GET /api/public/firm` under the `Content` module, but `firms` belongs to `Firm` (a module writes and serves only its own tables) → `PublicFirmController` + `PublicFirmResource` in `Firm` (to reverse: move the two classes).
- W17 F2: MySQL still unavailable (W1) → `migrate:fresh --seed` and the live check ran on a throwaway SQLite file with the `Makefile.local` targets and `DB_CONNECTION`/`DB_DATABASE` overridden; the three new migrations use no MySQL-only SQL (AM11) (to reverse: `make fresh-seed` on MySQL once the password is set).
- W18 F2: AM15 allows site strings in pt-BR only, but `fallbackLocale` is `en` and a visitor with English stored would see raw keys, and Vitest runs in English → every site key exists in both locales; the e2e site spec seeds pt-BR (to reverse: drop the `en` site keys and seed pt-BR in every Site spec).
- W19 F2: two sector pages listed the 28/10/2026 TCU fact after 2027 (the demo's order) → facts in date order in `SectorPageSeeder` (to reverse: reorder the arrays).
01:27 F2 done, attempt 1, 6734fc7
01:28 F3 started, attempt 1
