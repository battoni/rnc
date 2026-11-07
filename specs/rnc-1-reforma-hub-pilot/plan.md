# Plan: Franco Reforma Hub — the real pilot (Phase 1)
ID: rnc-1 · Spec: `specs/rnc-1-reforma-hub-pilot/spec.md` (closed 07/11/2026)
Approved: 07/11/2026 (Claude, owner away — `/battoni-run` mode, W# logged in `state.md`)

Every phase: Pest tests red first for the API, then Vitest for the SPA, then code; then
`make pre-commit` green; then commit `rnc-1: F<n> <what>`. API code follows
`.claude/rules/arcus-api-architecture.md` (modules under `api.rnc/app/Modules/<Name>/` with
Controllers · Requests · DTOs · Services · Repositories · Resources · Models · RULES.md ·
CHANGELOG.md); SPA code follows `.claude/rules/celer-0*.md` (modules under
`app.rnc/src/modules/<Name>/`, PrimeVue + the theme in `src/styles/theme/`). Strings pt-BR in
`locales`, with the `en` keys the e2e suite needs. Behaviour reference: the demo
(`/Users/battoni/Work/battoni.dev/dev/demos/franco-reforma-hub/src/` — `engine.js` rules,
`seed.js` content, `views/*.js` screens); port, don't paste.

## Phases

**F1 Foundation** (R1–R4)
- Remove `Idea`, `Tip`, `Showcase` (API + SPA, full test surface) and the `Home` sample content;
  keep `Auth`, `User`, `Personalization`.
- `Firm` module: migration, model, `BelongsToFirm` trait + global scope (current firm from the
  authenticated user; for public routes from the `X-Firm` header or the default firm slug
  `franco`), `FirmSeeder` with Franco from `facts.md` §1.
- Roles `operador`, `ceo`, `equipe` (+ `users.is_specialist`, `users.firm_id`) in the role seeder;
  `LocalUsersSeeder` with one user per role and 4 specialists (invented names). Passwords go in
  `state.md` → Local environment.
- SPA: a `franco` theme (navy `#00375a`, cyan `#14c3ff`) set as `VITE_ACTIVE_THEME`; `PublicLayout`
  (header with "Franco Contabilidade", footer with the public contact) and the back-office layout
  with a role-filtered rail (Equipe · Reinaldo · Operador), empty home per role.
- Local env: create MySQL database `rnc`, `api.rnc/.env` and `app.rnc/.env` (API on
  `http://127.0.0.1:8000` via `php artisan serve`, SPA on `http://localhost:5173`, Sanctum
  stateful domain `localhost:5173`, CORS), `Makefile.local` targets `serve-api`, `serve-app`,
  `fresh-seed`. Update `CLAUDE.md` + `.cursor/rules/project-context.mdc` to this product.
- Tests: A1 (firm isolation on a sample firm-scoped model), A2 (login per role, rail).

**F2 Site** (R5–R8)
- API `Content` module: `sector_pages` (firm, slug, title, lede, facts[] with date + source,
  seo) seeded with the 6 sectors from the demo `seed.js`; `GET /api/public/sectors`,
  `GET /api/public/sectors/{slug}`, `GET /api/public/firm` (brand + contact).
- API `Tracking` module: `visitors` and `events` tables (visitor uuid, type, path, sector, source,
  medium, lead_id nullable, at), `POST /api/public/events` (throttled 60/min, validated types).
- SPA `Site` module: landing (hero, countdown, proof points, o que muda, timeline), sector pages,
  `useTracking` composable (visitor id, source from utm/referrer), `useSeo` (title/meta/OG).
- Tests: A3 (E2E with the API mocked), event endpoint validation and throttle (Pest).

**F3 Diagnostic and result** (R9–R14)
- API `Diagnostic` module: `CnpjLookup` contract + `BrasilApiCnpjLookup` (Http, 5 s timeout,
  30-day cache) + CNPJ check-digit rule; `RulesEngine` porting `diagnose()`, `taxMix()`,
  `splitPaymentRange()` with the bands/sectors/regimes; `Lead` module: `leads` (firm, visitor,
  company, cnpj, contact phone/email, profile json, stage, last_step, channel, source),
  `lead_consents` (text, version, given, at, via), `lead_events` (timeline).
  Public endpoints: `POST /api/public/diagnostics` (step 1 → creates the partial lead and returns a
  signed lead token), `PATCH /api/public/diagnostics/{token}` (later steps), `GET
  …/{token}/result` (count, titles, ranges — no details), `GET …/{token}/simulation`.
- SPA `Diagnostic` module: CNPJ/quiz switch, the lookup sequence, steps with the contact + consent
  after step 1, the result, the simulator chart (PrimeVue Chart or plain SVG bars), tracking calls.
- Tests: A5, A6 (API side), A7, A8; Vitest for the step flow.

**F4 Booking and notifications** (R15–R17)
- API `Booking` module: `specialist_hours` (weekday, from, to), `appointments` (unique specialist +
  starts_at), `Holidays` helper (national fixed + Easter-based movable dates), availability and
  booking endpoints on the lead token, the lead moves to `reuniao_marcada`.
- Mail: `BookingConfirmed` and `MeetingReminder` Mailables (queued, branded markdown), the signed
  `GET /api/public/reports/{lead}` + SPA `/relatorio/:lead` printable page, the
  `appointments:remind` command scheduled hourly (`reminded_at` guards the once).
- Database notifications `NewLead` and `MeetingBooked` to every `equipe`/`ceo` user of the firm;
  `GET /api/notifications`, mark read; SPA bell with the count, polled every 30 s.
- Tests: A9, A10; Vitest for the slot picker and the bell.

**F5 Pipeline** (R18–R20)
- API: `GET /api/leads` (by stage, search), `GET /api/leads/{id}` (full diagnostic), `PATCH
  /api/leads/{id}/stage` (lost needs a reason; logs a `lead_events` row with the user),
  `POST /api/leads/{id}/notes`, `GET /api/agenda?week=`.
- SPA `Pipeline` module: kanban (drag with `vuedraggable` or native DnD + buttons), lead detail
  (profile, diagnostic, timeline, notes, `tel:`/`mailto:`/`wa.me` actions, consent), agenda week.
- Tests: A11; Vitest kanban move and lost-reason dialog.

**F6 Governance** (R21–R23)
- API `Partner` module: `partners` (firm, name, specialty, email, phone, active), seeded with the
  demo's 8 "(exemplo)" partners; CRUD for `ceo`/`operador`; `GET /api/leads/{id}/partner-matches`
  (port `matchPartners()`/`PARTNER_RULES`); `POST /api/leads/{id}/referrals` (422 without
  consent; e-mail to the partner `ReferralSent`); `GET /api/referrals` (trail, `ceo`/`operador`).
- SPA: routing panel on the lead detail, partners admin screen, trail screen.
- Tests: A12, authorisation of the trail (A2 part).

**F7 Metrics** (R24)
- API `Metrics` module: `GET /api/metrics/funnel?days=7|30` (distinct visitors/leads per step
  from `events` + `leads`, by source and by sector page) for `ceo`/`operador`; a `MetricsSeeder`
  with 30 days of invented traffic and leads (local only) so the screen is not empty.
- SPA `Metrics` module: funnel bars with drop-off, source and sector tables, 7/30 toggle.
- Tests: A4.

**F8 Fee repricing** (R25–R26)
- API `Portfolio` module: `clients` (firm, name, cnpj, sector, regime, band, b2b_share,
  monthly_fee, repricing json), CSV import (all-or-nothing, line errors), template download,
  `ClientSeeder` with 500 invented clients (local), `GET /api/repricing` (filters, totals),
  `GET /api/repricing/{client}` (items), `POST /api/repricing/mark` (batch), letter endpoint.
- SPA `Repricing` module: list with filters and totals, detail drawer, printable letter page,
  batch select + mark, CSV import dialog.
- Tests: A13, A14.

**Verify** — `/battoni-verify rnc-1`: full suites, `fable-reviewer` on the diff (`main..`),
fixes, then the Chrome rounds (A3, A6, A15 and a full visitor → team → CEO walk) against
`php artisan serve` + `npm run dev` on the seeded MySQL database.

## Defaults
Slots 30 min · 10 working days · Franco's hours · reminder 24 h · bell poll 30 s · report link 30
days · tracking throttle 60/min · repricing weights 1,5/2,5, 4–25 %, R$ 50 rounding · partner
match: 70 % for one covered item + 11 per extra, cap 97 %, uncovered 12–28 % (demo W8).

## Amendments from the plan review (fable-reviewer, 07/11/2026) — these OVERRIDE the spec and the phases above
- **AM1 Local hosts (F1).** One host only: API `http://localhost:8000` (`php artisan serve --host=localhost --port=8000`), SPA `http://localhost:5173`. `.env`: `APP_URL=http://localhost:8000`, `FRONTEND_URL=http://localhost:5173`, `VITE_API_URL=http://localhost:8000`, `CORS_ALLOWED_ORIGINS=http://localhost:5173`, `SESSION_DOMAIN=null`, `SESSION_SAME_SITE=lax`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`. Never mix `127.0.0.1` and `localhost`. The bootstrap's session-cookie auth needs no Sanctum stateful-domain change.
- **AM2 Tracking (F2).** `POST /api/public/events` accepts only `{visitor, type, path, sector?, source, medium, lead_token?}`; `lead_id` never comes from the client — the API resolves it from the lead token and ignores an unknown token. `visitor` is a UUID v4; `type` ∈ `visit, cta, diag.start, diag.contact, diag.result, booking`; `path` ≤ 255; `sector` a known slug; the raw referrer is not stored, only the derived source. Throttle 60/min per IP. `events.lead_id` nullable WITHOUT a foreign key in F2 (F3 may add it). A bot-inflatable funnel is a known gap in `Tracking/RULES.md`.
- **AM3 Lead token (F3).** `leads.token` = `Str::random(48)`, unique, `token_expires_at` = +30 days. Every public `…/{token}` route answers 404 for an unknown or expired token. Public answers go through a `PublicLeadResource` allow-list: never phone, e-mail, contact name or internal `id`. After `reuniao_marcada`, a profile PATCH is refused (409).
- **AM4 Report (F4).** `leads.report_token` (`Str::random(48)`) + `report_expires_at` (+30 days), created at booking. The e-mail links `FRONTEND_URL/relatorio/{report_token}`; the SPA calls `GET /api/public/reports/{report_token}` (404 if invalid/expired). Content: company name, result (titles + ranges, no details), the 2026–2033 simulation, the meeting (specialist, date). No lead contact data on it. No `temporarySignedRoute`.
- **AM5 E-mail at booking (F4).** If the lead has no e-mail, the booking step asks for it (required) before confirming; the API answers 422 `email_required` without one. The confirmation screen also shows the report link.
- **AM6 Firm scope (F1, F4).** `BelongsToFirm`'s scope reads a `CurrentFirm` container binding, set by middleware from the authenticated user or the `X-Firm` header (default `franco`). Commands iterate `Firm::all()` and set the binding per firm; queued jobs receive the model and load with `withoutGlobalScope` only. In Phase 1 the `operador` belongs to firm 1 like everyone; multi-firm access is Phase 3. A1 also covers a command.
- **AM7 Notifications table (F4).** F4 starts with `php artisan make:notifications-table`.
- **AM8 Permissions (F1 and on).** Use the bootstrap's `permission:<perm>` middleware, not a parallel `role:` one. `equipe`: `leads.read, leads.update, agenda.read, notifications.read`; `ceo`: equipe + `partners.manage, referrals.read, metrics.read, repricing.manage, clients.import`; `operador`: everything. The rail filters by permission. A2's 403s are tested in the phase that adds each endpoint (trail F6, metrics F7, repricing F8).
- **AM9 Time (F4).** Holidays (closed list): 01/01, Carnaval Monday and Tuesday, Good Friday, 21/04, 01/05, Corpus Christi, 07/09, 12/10, 02/11, 15/11, 20/11, 25/12; Easter by Meeus. Hours computed in `America/Sao_Paulo`, `starts_at` stored in UTC, the API returns ISO-8601 with an offset; tests use `Carbon::setTestNow`.
- **AM10 BrasilAPI mapping (F3).** ME → band ≤ 360k; EPP → 360k–4,8M; DEMAIS/null → band asked. `opcao_pelo_mei` → MEI; `opcao_pelo_simples` true → Simples, false/null → regime asked. Sector from the first 2 digits of the main CNAE: 01–03 agro, 05–33 indústria, 45–47 comércio, 85 educação, 86–88 saúde, else serviços. Check digits validated BEFORE the call; throttle 10/min per IP; cache key `cnpj:{14 digits}`.
- **AM11 Portable SQL (all).** No raw SQL in migrations or in Metrics/Booking: dates in Carbon + `whereBetween`; no `enum` columns (string + validation), no `fulltext`; `json` is fine.
- **AM12 Consent (F3).** Checkbox unticked by default; every contact submission writes a `lead_consents` row with `given` true/false; `text` = the demo's `consentText` (`engine.js` ~572), `version = 1`, `via = 'formulario'`; no IP or user-agent.
- **AM13 Stages (F3, F5).** Slugs `visitante, lead_parcial, lead, reuniao_marcada, proposta, cliente, perdido`. `POST diagnostics` creates `visitante` (no PII); PATCH with contact → `lead_parcial`; GET result → `lead`; booking → `reuniao_marcada`; the rest only by the team.
- **AM14 Module removal (F1).** API: remove `Idea`, `Tip`, the migrations `tips*`, `ideas*`, `idea_*`, `news*` (grep for references first); the role seeder gets the three roles. SPA: remove `Idea`, `Tip`, `Showcase` and the `Home` sample content.
- **AM15 Public SPA routes (F1–F4).** The `Site` routes, the public diagnostic and `/relatorio/:token` carry `meta: { public: true }`, outside the auth guard. The e2e `en` keys are needed for the back office only.
