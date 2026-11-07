# State: Franco Reforma Hub — the real pilot
Stage: build F1/8
Updated: 07/11/2026
## Done
Intent approved 07/11 · spec closed 07/11 (26 R · 15 A · 9 D) · plan approved 07/11 (F1–F8) + plan-review amendments AM1–AM15. Bootstrap configured as rnc (9069414).
F1 Foundation (R1–R4, A1, A2): Idea/Tip/Showcase + Home sample removed with their full test surface (API modules, migrations `tips*`/`ideas*`/`idea_*`/`news*`, factories, seeders, routes, limiters, SPA modules, aliases, locale keys, `ATip`/tip store/tutor toggle, features catalogue, e2e specs); `Firm` module (`firms`, `CurrentFirm`, `ResolveCurrentFirm`, `FirmScope`, `BelongsToFirm`, `FirmSeeder`); roles `operador`/`ceo`/`equipe` with the AM8 keys, `users.firm_id` + `is_specialist`, `LocalUsersSeeder`; SPA theme `franco`, `ThePublicLayout`, text `ALogo`, `Site` module (open `/`), back office `home` at `/painel` + three workspaces and the permission-filtered rail; local env (.env files, `Makefile.local`); `CLAUDE.md` + `project-context.mdc` describe the product. Suites: 345 Pest · 747 Vitest · 85 Playwright, all green; A1 proven red with the scope disabled (7 of 10 failed). pre-commit green 07/11/2026.
## Next
`/battoni-start-session rnc-1` → F2 Site (R5–R8). First, the owner (or the next session, if it has the password) runs `make fresh-seed` against MySQL — see W1.
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
