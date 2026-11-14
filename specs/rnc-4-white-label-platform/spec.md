# Spec: Reforma hub as a white-label platform for accounting firms
ID: rnc-4 · Status: closed 13/11/2026 (product decisions O1–O12 by the owner on 13/11/2026; D# taken by Claude in away mode, each says how to reverse) · Intent: approved 13/11/2026
Proposal: none — skipped in away mode (D27). Visual bar: the public site of rnc-1/2/3 (motion
directives, brand ramp, `ThePublicLayout`), the back office's existing atoms/molecules.

Builds on rnc-1, rnc-2 and rnc-3 (all merged): their rules, amendments and RULES.md still hold
unless a line here replaces them. "Platform firm" = the owner's "firm zero": the product itself as
a tenant, served at the root domain. "Root" = the root domain (`localhost` locally,
`PLATFORM_ROOT_DOMAIN` in production). "Manager" = today's `ceo` role, renamed `gestao` (R14).

## Owner decisions (13/11/2026, quoted from the brief)
O1 Niche: accounting firms + Reforma; generic later. O2 The demo shows a FICTIONAL firm; Franco stays
a tenant (`franco`). O3 Tenant by SUBDOMAIN (`<slug>.<root>`), custom domains later; locally
`<slug>.localhost:5173`, never 127.0.0.1. O4 Product name = placeholder in ONE config value. O5
Platform operator with NO firm; enters a firm only through an audited, bannered "view as" that ends
on logout. O6 Consent text edited by the firm's manager, default set by the operator, every edit a
new version, stored consents keep theirs. O7 The root serves the product's own site with "join the
beta"; signups are leads on the platform firm's pipeline (new `LeadChannel`). O8 `firm_brands` stay
sub-brands; the runtime-brand machinery is lifted to the FIRM for site and back office. O9 Scope and
priority F1 → F2 → F3 → F4a → F6 → F5 → F4b → F7 → verify (→ optional F9). O10 Gate cost: touched
modules per phase, full gate at F2, F4a and verify. O11 Away run: draft PR after verify ·
fable-reviewer · Chrome QA on. O12 QA rounds by role (plan `## QA`).

## Requirements

**Firm by host (O3, O5, O7)**
- R1. A firm's public site, back office and partner portal are served at `<slug>.<root>`; the root
  and `www.<root>` serve the platform firm. (D1, D2, D3)
- R2. The SPA reads its host once at boot: on `<slug>.<root>` every API call (public and
  authenticated, through the one API provider) carries `X-Firm: <slug>`; at the root it carries none
  and the API answers the default firm (`DEFAULT_FIRM_SLUG`, the platform firm's slug outside
  testing). (D3, D6)
- R3. An unknown or reserved subdomain: the API answers 404 `firms.notFound`, the SPA shows a calm
  "Escritório não encontrado" page with a link to the root. (D2)
- R4. Sign-in (password and phone code) succeeds only when the account's firm is the request's firm;
  a mismatch gets the same `auth.login.invalidCredentials` as a wrong password. A partner's portal
  login follows its firm the same way. The platform operator (no firm) signs in only at the root. (D5)
- R5. A signed-in person whose request carries an `X-Firm` naming another firm gets 403
  `firms.mismatch` and the SPA signs out locally. A signed-in person with no firm never falls through
  to the header or the default firm: outside view-as (R26) no firm is current and firm data is empty
  or 404. (D5, D20)
- R6. The SPA calls the API same-origin at `/api` (Vite proxy locally, the web server in
  production), so each firm host holds its own host-only session cookie. (D4)
- R7. Self-registration is closed: `auth/register`, `auth/email/code`, `auth/email/verify` answer 404;
  accounts are made by `firm:create`, the console or the firm's manager. (D21)

**One brand per firm (O4, O6, O8)**
- R8. `firms` gains `short_name`, `tagline`, `proof` (four `{value, label}`), `logo_path`, `modules`,
  `is_platform` (exactly one row true); `firm_brands` stays the sub-brands of a firm. (D7, D8)
- R9. The product name is one config value — `config('platform.name')`, env `PLATFORM_NAME`, default
  "Hub da Reforma" — served as `platform.name` in `GET public/firm` and `GET auth/me`; no other
  file names the product. (D9)
- R10. One app-level brand composable paints the request firm's palette, logo (or logo text) and
  names on BOTH layouts, from the tab's last answer before the API replies (no flash); a sub-brand
  still overrides only public pages. The back office no longer depends on `VITE_ACTIVE_THEME` for
  its brand colours. (D10)
- R11. A firm's primary colour must give white text 4.5:1 (`Contrast`), on every write that sets it.
- R12. Every e-mail takes the brand of the firm it concerns: from-name = firm name, reply-to = firm
  mailbox, band colours = firm palette, logo = firm logo; `config('rnc.brand')` is removed. (D11)
- R13. Consent text is per firm and versioned: an edit creates version n+1 and never touches older
  ones; a diagnostic or chat answer stores the firm's current version number and its text (with
  `{escritorio}` filled by the firm's — or the sub-brand's — name); a new firm starts at version 1
  copied from the platform default. (D12)
- R14. Back-office routes `/reinaldo/*` (en `/ceo/*`) become `/gestao/*` (en `/management/*`); role
  `ceo` becomes `gestao`, labelled "Gestão"; strings that need the firm take `{firm}` from the brand
  composable. (D13)
- R15. No code, config, locale, template or route outside seeders, tests, fixtures, docs and comments
  names Franco, Reinaldo, Willy or APW; "RNC" appears only inside the Channel module. (D14)

**Modules per firm (O9 F3)**
- R16. A firm has a module set: core always on — Firm, Auth/User, Site + Content, Tracking,
  Diagnostic, Lead, Booking, Notification, Pipeline, Metrics; opt-in — `portfolio` (clients,
  repricing), `fiscal` (NF-e), `radar`, `partners` (network, portal, ledger, referrals),
  `contracts`, `chat` (receptionist), `assistant` (staff), `briefing`, `brands` (sub-brands);
  Franco-only — `channel` (off for everyone else). (D15)
- R17. A disabled module's API routes answer 404 (`module:<key>` middleware, public routes
  included; the chat channel of the diagnostic answers 422 `modules.disabled`), its scheduled work
  skips the firm, its screens leave the rail and its SPA routes show not-found.
- R18. Module dependencies hold on every write: `radar`, `contracts` and `fiscal` need `portfolio`;
  violating one is 422 `modules.missingDependency`; `channel` is settable by the platform operator
  only. (D16)
- R19. Modules that read other modules degrade, never 500: Briefing omits sections and actions whose
  modules are off (referrals/SLA ← partners; fee bands ← portfolio; Radar action ← radar; contract
  action ← contracts); Radar hides XML evidence without `fiscal` and batch referral without
  `partners`; Contracts hides the legal referral without `partners`.
- R20. `GET auth/me` returns the firm (`slug`, `name`, `modules`, brand summary) or `null`, plus
  `platform` (`name`, `is_operator`, `view_as`).
- R21. `php artisan firm:create {slug} {--name=} {--manager=} {--email=} {--specialist}` creates the
  firm (default palette, modules core + `briefing`, the default sector pages, consent v1 from the
  platform default), its manager (`gestao`) with a one-time password printed once, and specialist
  hours from business hours; it refuses a reserved, malformed or taken slug. (D17)
- R22. Locally the fictional demo firm "Alvorada Contabilidade" (`alvorada`) is seeded with invented
  contact data and palette, every opt-in module but `channel` and `brands`, and rich invented data:
  manager, team, specialists with hours, ≥ 40 leads over 30 days, meetings, partners with portal
  logins and referrals, funnel traffic, ≥ 200 portfolio clients, contracts, NF-e, Radar, assistant
  notes. (D18)
- R23. The platform firm (`hub`) is seeded in every environment with `is_platform`, name =
  `platform.name`, core modules only, and locally a manager login for its pipeline.

**Platform operator (O5)**
- R24. The platform operator is a user with role `operador` and `firm_id` null (the seed moves
  `battoni` there); it signs in at the root and, outside view-as, sees only the platform console.
  Roles and permission matrices stay platform-wide and operator-only.
- R25. The console lists firms (name, subdomain link, modules, created, lead count), creates a firm
  (the same service as `firm:create`), edits its look (name, logo text, short name, tagline, proof,
  colours — and its sub-brands when `brands` is on), sets its modules, creates or resets its
  manager's login (password shown once), and edits the platform default consent text. (D19)
- R26. View-as: from the console the operator enters one firm; while active the API scopes every
  request to it and the back office shows a fixed banner "Você está acessando <firma> como operador
  da plataforma · Sair"; it ends on "Sair", on logout, or when the session ends; each start and end
  is logged (operator, firm, started, ended, reason) and listed in the console. (D20)
- R27. Logo upload (console): PNG, JPEG or WebP, ≤ 512 KB, ≤ 1024 px a side, content-sniffed; SVG and
  anything else 422; stored under a server-made name on the public disk; shown in both layouts and
  embedded in e-mails. (D22)

**Product site on the root (O7)**
- R28. The root's public site is the product's landing for accounting firms: what the hub does for a
  firm (diagnostic → booking → pipeline → metrics, then the opt-in modules), the Reforma's urgency
  (CBS on 01/01/2027, the existing countdown), the module menu, how the beta works (few firms, about
  three months, promo price on request), FAQ, the CTA "Quero participar do beta", and a link to the
  demo firm. The diagnostic never shows at the root; firm hosts keep it. (D23, D24)
- R29. The beta form (firm name, contact name, role, e-mail, phone, city/UF, clients band, optional
  message, LGPD consent required) creates a lead on the platform firm only — channel `beta`, stage
  `lead`, answers in `profile`, the consent with the platform firm's current version — rings the
  platform firm's team (bell + e-mail), and is throttled; the route is 404 on any other firm. (D29)

**Firm settings (O6, O9 F5)**
- R30. The manager (`firm.settings`) edits the firm's contact data: address, phone, mailbox, business
  hours — not the look nor the modules (operator's). (D25)
- R31. The manager manages the firm's team logins only: list, create (`equipe` or `gestao`, specialist
  flag), deactivate/reactivate, reset password (shown once); the user endpoints are scoped to the
  caller's firm for everyone but the platform operator; a user of another firm is 404.
- R32. The manager edits each specialist's weekly hours; a newly flagged specialist starts on the
  firm's business hours.
- R33. The manager sees the consent text's current version and history (version, date, author),
  edits it into a new version with a preview, and stored answers keep their version.

**Deploy readiness (O9 F7) — no deploy**
- R34. `docs/deploy/runbook.md` lists every env key the API config and the SPA read, wildcard DNS +
  TLS, the web server's same-origin `/api`, Sanctum/session/CORS values, the queue worker and the
  scheduler with each scheduled command, per-firm mail, backups, and that folded migrations end at
  the first deploy. (D26)
- R35. `GET api/health` answers 200 `{status: "ok", db: "ok"}` (503 when the database is down), with
  no auth and no firm data.
- R36. The full Pest suite runs on MySQL 8 when a local login works; otherwise a W# says so and the
  suite runs on SQLite.

**Access**
- R37. Every new route is classified in `tests/Feature/Access/routeClasses.php`; a cross-firm sweep
  proves each new firm-scoped route is 404 for another firm and refused for the wrong role.

**Optional (F9, only if quota remains)**
- R38. Per-firm privacy notice and data-processing page from an operator template + the firm's data.
- R39. The manager exports a lead's data (JSON) and anonymises a lead (personal fields cleared,
  consent rows kept with text) from the lead detail.
- R40. `firm:offboard {slug}` exports the firm's data and marks it inactive (host 404, logins refused).

## Acceptance criteria
Phase tags in brackets (plan.md). Every A# is proved in exactly one phase.

A1 (R1, R2) Given firms `franco` and `alvorada`, when a visitor with `X-Firm: alvorada` starts a
diagnostic, then the lead carries alvorada's firm and a signed-in Franco member's `GET leads` does
not list it while alvorada's manager's does. → Pest [F1a]
A2 (R3) Given no firm `xyz`, when `GET public/firm` carries `X-Firm: xyz` (or `www`), then 404
`firms.notFound`. → Pest [F1a]
A3 (R4) Given `patricia` (franco), when she signs in with the right password and `X-Firm: alvorada`,
then 422 `auth.login.invalidCredentials` and no session; with `X-Firm: franco`, 200. The phone-code
verify and a partner login refuse the same way. → Pest [F1a]
A4 (R5) Given `patricia` signed in, when a request carries `X-Firm: alvorada`, then 403
`firms.mismatch`; given a signed-in user with no firm, when it calls `GET leads` with or without
`X-Firm`, then it reads no firm's rows. → Pest [F1a]
A5 (R7) When `POST auth/register` (or the email-code routes) is called, then 404. → Pest [F1a]
A6 (R2, R6, R3) Given the SPA on `alvorada.localhost`, then every call goes to `/api` with
`X-Firm: alvorada`; on `localhost` and `www.localhost` none; an API 404 `firms.notFound` at boot
renders "Escritório não encontrado". → Vitest [F1b]
A7 (R5, R6) Given the SPA served on `franco.localhost:<port>` with the API mocked, when a call answers
403 `firms.mismatch`, then the session store is cleared and the login screen shows. → Playwright [F1b]
A8 (R8, R9) Given alvorada with short name, tagline and proof, when `GET public/firm` (X-Firm alvorada),
then it answers them, `is_platform: false`, `logo_url: null` and `platform.name` = config; with
`PLATFORM_NAME=Outro` it answers "Outro". → Pest [F2a]
A9 (R12) Given a meeting booked on alvorada, when the confirmation is rendered, then the from-name is
Alvorada's, reply-to its mailbox, the band its primary, and the HTML and text contain no "Franco";
the same for the referral and briefing mails. → Pest [F2a]
A10 (R13) Given franco at consent v1 with a stored answer, when a v2 text is saved, then v1 is
unchanged, a new diagnostic stores v2 and v2's text, under the `prime` sub-brand the text names
"Franco Prime…" with version 2, and the old answer still reads v1's text. → Pest [F2a]
A11 (R10) Given the SPA on alvorada's host, when the back office or a public page mounts, then
`<html>` carries alvorada's `--color-primary-*`, the rail and header show its logo text and
`platform.name`, a second mount paints the stored palette before the API answers, and a sub-brand
overrides only public pages. → Vitest [F2b]
A12 (R10, R11) Contrast specs pass on two palettes (franco's and alvorada's) in light and dark on the
landing, the diagnostic, the back-office home and the pipeline. → Playwright [F2b]
A13 (R14) Given a `gestao` user, `/gestao/briefing` and `/management/briefing` render, `/reinaldo/briefing`
is not found, and the rail group reads "Gestão". → Vitest [F2c]
A14 (R14) The seeded `gestao` role holds exactly the former `ceo` keys and no role `ceo` exists. → Pest [F2c]
A15 (R15) A Pest test over `api.rnc/{app,config,resources,lang,routes}` and a Vitest over `app.rnc/src`
(skipping specs, `test/` dirs and comment lines) find no `Franco|Reinaldo|Willy|APW`, and "RNC" only
under the Channel modules. → Pest + Vitest [F2c]
A16 (R15) Given alvorada's mocked firm, the landing, diagnostic result, report and back-office home
render no "Franco" in their text. → Playwright [F2c]
A17 (R16, R17) Given alvorada with `partners` off, `GET partners`, `GET portal/me`, `GET ledger` are
404 while `GET leads` is 200; with `chat` off a chat-channel diagnostic is 422 `modules.disabled`;
turning them on makes them 200. → Pest [F3a]
A18 (R17) Given a firm without `briefing`, `briefing:send` mails nobody of it; without `assistant`,
`assistant:notify-expiring` skips it. → Pest [F3a]
A19 (R18) Setting `radar` without `portfolio` is 422 `modules.missingDependency`; a non-operator
setting `channel` is 403. → Pest [F3a]
A20 (R19) Given `briefing` on and `portfolio`, `partners`, `radar`, `contracts` off, `GET briefing` is
200 with those sections null and no action of those kinds; given `contracts` on and `partners` off,
`GET contracts/legal-partners` is 404 and the alert answers no referral fields. → Pest [F3a]
A21 (R20) `GET auth/me` answers `firm.modules` and `platform.name`; for a firm-less operator `firm: null`. → Pest [F3a]
A22 (R21, R13) `firm:create acme --name="Acme Contábil" --manager=helena.acme --email=…` creates the
firm with core + `briefing`, six sector pages, consent v1 equal to the platform default, a `gestao`
manager whose printed password then signs in with `X-Firm: acme`; `www`, `Acme!` and `franco` are
refused. → Pest [F3b]
A23 (R22, R23) After the local seed, alvorada has ≥ 40 leads, ≥ 3 meetings, ≥ 4 partners with logins,
≥ 200 clients, contracts, NF-e and served notes, its address/phone/mailbox are not Franco's, and
`hub` is the one `is_platform` firm with core modules only; the demo seeder refuses production. → Pest [F3b]
A24 (R17, R20) Given `me.firm.modules` without `partners`, the rail has no partner entries and
`/gestao/parceiros` shows not-found. → Vitest [F3c]
A25 (R19) Given a briefing payload with the portfolio, partner, radar and contract sections null, the
briefing view renders its other sections and no error. → Vitest [F3c]
A26 (R24) `battoni` signs in at the root (no `X-Firm`): 200, `me.firm` null, `platform.is_operator`
true; with `X-Firm: franco` refused; `GET leads` outside view-as reads nothing. → Pest [F4a]
A27 (R25, R11) The operator's `POST platform/firms` creates a firm reachable by `GET public/firm`;
`PATCH platform/firms/{id}` with primary `#7fd3ff` is 422 `brand.lowContrast`; `PUT
platform/firms/{id}/modules` applies R18; `POST platform/firms/{id}/manager` returns a password once
and the manager signs in on its host; a `gestao` user gets 403 on every `platform/*`. → Pest [F4a]
A28 (R25, R13) `PUT platform/consent-default` changes what the next created firm copies and leaves
existing firms' versions as they were. → Pest [F4a]
A29 (R25) The console: firm list, `MAddEditFirmForm` in `MMainDialog` (slug rules, contrast refusal
shown on the colour field), module switches with the dependency message, password shown once with
copy. → Vitest [F4b]
A30 (R25) The operator creates a firm and opens its subdomain link at 390 and 1440, light and dark,
with no horizontal scroll. → Playwright [F4b]
A31 (R29) At the root, `POST public/beta-signups` creates a `beta` lead on `hub` with the consent's
version and rings hub's team; with `X-Firm: franco` it is 404; missing consent is 422; the 6th call
in a minute is 429. → Pest [F6a]
A32 (R28) At the root the public layout renders the product landing (product name from the API, the
beta CTA, the demo link) and never the diagnostic; on a firm host the diagnostic landing renders. → Vitest [F6b]
A33 (R29) The beta form validates each field, requires consent, shows the success state on 201 and
the API's field errors on 422; the board's card shows the "Beta" channel chip. → Vitest [F6b]
A34 (R28) The product landing at 390, 1024, 1280 and 1920 px has no horizontal scroll and passes
contrast in light and dark; the form submits against the mocked API. → Playwright [F6b]
A35 (R30) The manager's `PATCH firm/settings` changes address, phone, mailbox and hours seen by
`GET public/firm`; brand fields are ignored; `equipe` is 403. → Pest [F5a]
A36 (R31) The manager's `GET auth/users` lists only its firm; a created user gets the manager's firm
whatever the payload says; editing another firm's user is 404; a manager cannot touch roles
(`permissions.*` 403). → Pest [F5a]
A37 (R32) Editing a specialist's hours changes `GET public/diagnostics/{token}/availability`; a newly
flagged specialist gets the firm's hours. → Pest [F5a]
A38 (R33) The manager's `POST firm/consent-versions` creates v n+1 with its author; `equipe` 403;
stored answers keep their version and text. → Pest [F5a]
A39 (R30–R33) The settings screens (contact, team, specialists' hours, consent with history and
preview) send the right calls and render the API's errors. → Vitest [F5b]
A40 (R30–R33) A manager edits the consent and a team login at 390 and 1440, no horizontal scroll. → Playwright [F5b]
A41 (R27) A 300 KB PNG uploads and `public/firm.logo_url` serves it; an SVG, an SVG renamed `.png`, a
600 KB JPEG and a 2000 px PNG are 422; a non-operator is 403. → Pest [F4c]
A42 (R26) The operator's `POST platform/firms/{id}/view-as` logs a row and makes `GET leads` that
firm's; `DELETE platform/view-as` closes it with reason `sair`; logout closes it with `logout`; a
firm user starting one is 403; `GET platform/view-as-log` lists both. → Pest [F4c]
A43 (R26, R27) While `me.platform.view_as` is set the back office shows the banner with the firm's
name and "Sair" ends it and returns to the console; a firm with `logo_url` shows the image in both
layouts; the upload field refuses an SVG before sending. → Vitest [F4d]
A44 (R35) `GET api/health` → 200 `{status: ok, db: ok}` with no session and no firm field. → Pest [F7]
A45 (R34, R36) Every `env('…')` key under `api.rnc/config` and every `VITE_*` key in
`app.rnc/.env.example` appears in `docs/deploy/runbook.md`, and its "Database" section states the
MySQL run's Pest count or why it did not run. → Pest [F7]
A46 (R37) Every route of `routes/api*.php` is classified, and the sweep covers each route added by
rnc-4. → Pest [F7]
A47 (R38) Given alvorada, `/privacidade` renders the template with alvorada's name and contact. → Vitest [F9]
A48 (R39) Export answers the lead's fields and consents; anonymise clears name, e-mail, phone, CNPJ
and keeps consent rows; `equipe` 403; another firm 404. → Pest [F9]
A49 (R40) After `firm:offboard acme`, `GET public/firm` for acme is 404 and its manager is refused. → Pest [F9]

## Decisions (D# taken by Claude on 13/11/2026, owner away; each says how to reverse)
D1 Tenant address → subdomain `<slug>.<root>` (O3). Rejected: path prefix (`/<slug>`) — every SPA
route and link would change. Reverse: n/a (owner's).
D2 Firm ↔ host → the slug IS the subdomain; no `firms.domain` column yet. Reserved slugs: `www`,
`api`, `app`, `admin`, `hub`, `mail`, `static`, `assets`, `docs`, `status`, `beta`, `demo`,
`suporte` (`config/platform.php`). Reverse: add `firms.domain` (unique, nullable) and let the
resolver try it first — the custom-domain step.
D3 The platform firm → a row `slug = hub`, `is_platform = true`, served at the root and `www`; `hub`
is reserved so `hub.<root>` is never a tenant. Reverse: rename the slug in `PlatformFirmSeeder` and
`DEFAULT_FIRM_SLUG`.
D4 How the SPA reaches the API → same-origin `/api` (Vite `server.proxy` locally with
`allowedHosts: ['.localhost']`; the web server's vhost for `*.<root>` in production), host-only
session cookie per subdomain, `SANCTUM_STATEFUL_DOMAINS=localhost:5173,*.localhost:5173`. Why:
`*.localhost` hosts are different sites, so a Lax cookie from `localhost:8000` never rides a fetch
from `franco.localhost:5173` (facts §2), and in production a `.root` cookie would carry firm A's
session to firm B's host. Rejected: absolute API + `SESSION_DOMAIN=.<root>` + CORS patterns.
Reverse: absolute `VITE_API_URL`, CORS pattern `^https?://[a-z0-9-]+\.<root>$`, `SESSION_DOMAIN`.
D5 Firm mismatch → sign-in answers the wrong-password sentence (no enumeration); an authenticated
request is checked only when `X-Firm` is present (the SPA always sends it on firm hosts), so the
1851 Pest tests that act as a firm-B user without a header keep their 404s. Reverse: also compare
the default firm when the header is absent.
D6 Header-less default → `DEFAULT_FIRM_SLUG`: `hub` in `.env`/`.env.example`, `franco` pinned in
`phpunit.xml` so existing tests keep their implicit firm. Reverse: flip phpunit.xml and give every
test an explicit firm.
D7 Sub-brands → `firm_brands` unchanged in meaning (O8); `firms` copies their look columns so the
main brand and a sub-brand share one shape. Reverse: n/a.
D8 Schema changes → folded into the `create_*` migrations (never deployed; arcus §3) — this ends at
the first deploy (R34). Reverse: n/a.
D9 Product name → `config/platform.php` `name` (env `PLATFORM_NAME`, "Hub da Reforma"); the SPA
reads it from the API, `index.html` carries a neutral `<title>` until then. Reverse: one env line.
D10 App-level brand → `useBrandTheme`, `brandPalette`, `publicBrand` move to
`app.rnc/src/composables/brand/` (`useFirmBrand` + store), the Site module re-exports; the neutral
theme `hub.css` replaces `franco.css` as `VITE_ACTIVE_THEME` default (franco's colours come from its
row). Reverse: set `VITE_ACTIVE_THEME=franco` back (the file stays as `hub.css` values).
D11 Per-firm mail → from address = the platform's verified `MAIL_FROM_ADDRESS`, from-name = firm
name, reply-to = firm mailbox. Rejected: per-firm sending domains (DNS per firm; later). Reverse: a
`firms.mail_from` column.
D12 Consent storage → `firm_consent_versions` (BelongsToFirm: version, text, author user id
nullable, created_at; unique firm+version) + the platform default in a new `platform_settings`
key/value table (`consent_default`); `{escritorio}` filled at answer time; `lead_consents` keeps
storing version + text (no FK). The contract consent becomes one generic sentence with
`{escritorio}`, not versioned per firm. Reverse: per-firm contract text as a second key.
D13 Rename → `/gestao` + `/management`, role slug `gestao` "Gestão"; no redirects from old paths
(never deployed). Reverse: add redirect routes.
D14 Names guard → Pest + Vitest scans (A15), comment lines allowed. Reverse: delete the two tests.
D15 Module storage → `firms.modules` json list of opt-in keys; core is code, never stored. Reverse:
a `firm_modules` table if per-module settings appear.
D16 Dependencies → `radar`, `contracts`, `fiscal` → `portfolio`; Radar without `fiscal`/`partners`
degrades instead of requiring them. Reverse: edit `FirmModules::DEPENDENCIES`.
D17 `firm:create` defaults → primary `#1f4e79`, accent `#f2a900`, modules core + `briefing`, manager
not a specialist unless `--specialist`, password 16 random characters printed once. Reverse: flags.
D18 Demo firm → "Alvorada Contabilidade" (`alvorada`), Rua das Acácias, 120, Campinas-SP (invented),
primary `#0f5c4d`, accent `#f4b63f`; the Franco seeders become firm-parameterised and run once per
seeded firm; Franco-only seeds (channel, Express/Prime) stay Franco's. Reverse: `DemoFirmSeeder`.
D19 Who edits a firm's look → the operator only (console, view-as); the manager edits contact data.
Reverse: grant `firm.brand` to `gestao` and show the look form in settings.
D20 View-as → on the root host (no cross-host cookie), read and write allowed, audited at start and
end (not per action); a session that expires unclosed is closed with reason `expirou` at the
operator's next sign-in. Named "Acessar como suporte" to avoid rnc-3's "Ver como" (brand preview).
Reverse: a write guard middleware for read-only view-as.
D21 Self-registration → closed (routes kept in code, registered only when
`registration.enabled`, default false). Reverse: `REGISTRATION_ENABLED=true` (and give it a firm).
D22 Logo → PNG/JPEG/WebP, ≤ 512 KB, ≤ 1024 px, sniffed with `finfo`; no SVG (script-capable); not
re-encoded. Reverse: re-encode through GD to strip metadata.
D23 Beta price → none shown; "Preço promocional para os escritórios do beta — fale com a gente".
Reverse: a price line in the landing's locale.
D24 Demo link → the landing links "Veja um escritório de exemplo" to `alvorada.<root>`
(`VITE_DEMO_FIRM_SLUG`, empty hides it). Reverse: empty env.
D25 Settings scope → manager: contact, team, specialists' hours, consent; not look, modules, roles.
Reverse: see D19.
D26 Health → keep `/up`; add `api/health` with a `select 1`. Reverse: delete the route.
D27 proposal.html → skipped in away mode: it exists for the owner to approve a screen before it is
built, and he delegated every choice; the Chrome QA compares against this spec instead. Reverse:
`/battoni-new-proposal rnc-4 final` and re-run the affected Chrome round.
D28 Phase labels → the owner's phases keep their numbers with letter splits (F1a/F1b, F2a–F2c,
F3a–F3c, F4a/F4b console, F6a/F6b, F5a/F5b, F4c/F4d = his "F4b" logo + view-as, F7, F9a/F9b), run in
his priority order. Reverse: n/a.
D29 Beta lead shape → stage `lead`, `path = beta`, company/contact/e-mail/phone in their columns, the
rest in `profile`; bell kind `beta_signup` to hub's users with `leads.read`. Reverse: a separate
table if signups need fields the board cannot show.
D30 Gate cost → per phase only the touched modules' Pest/Vitest files + the phase's e2e files; full
gate (every Pest, Vitest, Playwright) at the end of F2c, F4b and in verify (O10). Reverse: n/a.
D31 Playwright host → default `baseURL` `http://franco.localhost:<port>`; product-site specs use
`http://localhost:<port>`. Reverse: one line in `playwright.config.ts`.

## Constraints, met by
- Cross-firm isolation → R4, R5, R6, R31, R37; A1, A3, A4, A36, A46, Chrome access sweep.
- Franco stays a working tenant → R16 (`channel` Franco-only), R22 (Franco seeds kept); full suites
  at F2c/F4b/verify; Chrome "Franco tenant" round.
- Consultant and his firm never appear; no real firm shown to prospects → R15, A15, A16; R22 (invented
  demo), A23.
- Generic CRM not the market → R16 keeps vertical modules separable; no generic content in scope.
- pt-BR, no horizontal scroll, contrast → A12, A30, A34, A40.
- No deploy, folded migrations → D8, R34, R36.
- Invented data only → R22, A23; every fixture in tests.

## Screens
Product landing — app: `/` on the root · widths 390, 1024, 1280, 1920.
Not-found firm — app: any path on an unknown subdomain · 390, 1280.
Platform console — app: `/plataforma/escritorios` (en `/platform/firms`), `/plataforma/consentimento`,
`/plataforma/acessos` · 390, 1440.
View-as banner — back office of the viewed firm · 390, 1440.
Firm settings — app: `/gestao/escritorio` (contact), `/gestao/equipe`, `/gestao/especialistas`,
`/gestao/consentimento` (en `/management/…`) · 390, 1440.

## Out of scope
Custom domains; billing; per-firm sending domains; generic funnel content; English product site;
the deploy; a per-action audit trail inside view-as; firm self-signup without the operator.

## Changes while building
(C# added by the build.)
- C1 (F1a, W6) D21's "registered only when `registration.enabled`" → the three routes stay
  registered and `EnsureRegistrationOpen` answers 404 while it is false. Same answer to the outside;
  the route matrix keeps classifying them and a test opens the door with a config change (routes are
  read once at boot). Reverse: wrap the three `Route::post` in `if (config('registration.enabled'))`.
- C2 (F1a, W7) The QA login-as (AM8) takes its firm from `X-Firm` when sent, else from the browser's
  Host (`<slug>.<root>`; root and `www` → default): a navigation through Vite's proxy sends no
  header. Local only. Reverse: header only, and the driver adds `X-Firm` some other way.
- C3 (F1a, W8) `POST auth/users` stamps the current firm on the new account (from F5a's create
  rules): with AM2's scoped reads, a firm-less new account vanished from the list that made it.
  F5a keeps the refusals (no `operador`, `firm_id` or `partner_id` from a manager). Reverse: n/a.
- C4 (F1b, W10) R2's "every API call carries `X-Firm`" includes the funnel events, which do not go
  through the API provider: on a firm host `recordEvent` sends a keepalive `fetch` with `X-Firm`
  instead of `navigator.sendBeacon`, which cannot carry a header (the API counted those on the
  platform firm). The root keeps the beacon. Reverse: beacon everywhere and a `firm` field in the
  event body read by the API.
- C5 (F2a, W14) D12's "Franco v1 = today's sentence" → Franco's v1 is its rnc-1 sentence with
  `{escritorio}` where its name was, filled like every firm's with the firm's — or the sub-brand's —
  full name: the main brand now reads "Autorizo a Franco Contabilidade a me apresentar…", the
  Express/Prime words are byte-identical to rnc-3's. A literal sentence would have dropped the
  sub-brand's name from its leads' consent. Reverse: fill the main brand with `short_name`.
- C6 (F2a, W16) R12's brand includes where an e-mail links: the report link, the briefing's links and
  the footer go to the firm's own host (`<slug>.<host>` of `rnc.frontend_url`; the root for the
  platform firm). A root link opened the platform's firm, where the firm's lead is absent.
  Reverse: `config('rnc.frontend_url')` again.
