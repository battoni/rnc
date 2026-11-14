# Facts: white-label platform (rnc-4)
Read 13/11/2026 on `feature/rnc-4-white-label-platform` (= main 33dad53 + tooling). Written by the
main session in away mode from the sources below (no Sonnet research pass — coordinator's brief).

## 1. How a firm is chosen today
- `ResolveCurrentFirm` (api middleware, appended after `EnsureAccountActive`,
  `api.rnc/bootstrap/app.php:44-48`): the signed-in person's `firm_id` wins; **with no firm on the
  user it falls through to `X-Firm`, then to `config('firms.default_slug')` = `franco`**
  (`api.rnc/app/Http/Middleware/ResolveCurrentFirm.php:34-53`, `api.rnc/config/firms.php`). An
  unknown header slug is 404 `firms.notFound`. So a firm-less signed-in user today lands on the
  default firm — the opposite of what a platform operator needs.
- `BelongsToFirm` + `FirmScope` scope every business model; no firm set → no rows
  (`api.rnc/app/Modules/Firm/RULES.md`, "How a query is scoped").
- The SPA never sends `X-Firm`: `publicApi` adds only `X-Brand`
  (`app.rnc/src/modules/Site/composables/publicBrand.ts:104-119`). Every visitor is Franco's.
- `users` is not firm-scoped (Firm RULES "Intentional Decisions"); `username` is unique platform-wide
  and `users.firm_id` is nullable (`api.rnc/database/migrations/0001_01_01_000000_create_users_table.php:16,30`).
  `GET auth/users` lists every user with no firm filter (`api.rnc/app/Modules/Auth/Controllers/UserController.php:30-40`).
- Self-registration is open: `POST auth/register` + email code (`api.rnc/routes/api.php:35-37`),
  creating a user with no firm.
- Sign-in: `AuthService::authenticate` checks username + password + status, then `Auth::login`
  (session cookie) — no firm check (`api.rnc/app/Modules/Auth/Services/AuthService.php:28-60`). A
  second path signs in by phone code (`api.rnc/routes/api.php:28-29`).

## 2. Hosts, cookies, CORS
- SPA calls an absolute API: `VITE_API_URL=http://localhost:8000/api` (`app.rnc/.env:2`,
  `app.rnc/src/constants/env.constant.ts:1`). No Vite proxy; Vite 7 (`app.rnc/package.json:83`),
  dev server bound to `VITE_APP_URL`'s host (`app.rnc/vite.config.ts:19-20,36`); no `allowedHosts`.
- Sanctum stateful domains from `SANCTUM_STATEFUL_DOMAINS` (`localhost:5173` locally,
  `api.rnc/.env:115`; `config/sanctum.php:21-24`). CORS origins from `CORS_ALLOWED_ORIGINS`, patterns
  from `CORS_ALLOWED_ORIGINS_PATTERNS` (empty), credentials on (`api.rnc/config/cors.php`).
  `SESSION_DOMAIN=null` (host-only cookie), `SESSION_SAME_SITE=lax` (`api.rnc/.env:36`, `config/session.php:159,202`).
- Browser fact (not read from code): every `*.localhost` host resolves to loopback in Chrome, and
  `franco.localhost` and `localhost` are different *sites* (no public-suffix entry), so a Lax
  session cookie set by `localhost:8000` is not sent on a fetch from `franco.localhost:5173`.
- Playwright serves its own `vite --mode test` on the checkout's `E2E_PORT`, API mocked
  (`Makefile:62-65`, `app.rnc/playwright.config.ts:3-8`); `.env.test` pins `VITE_ACTIVE_THEME=franco` (line 18).
- Health: Laravel's `/up` is registered (`api.rnc/bootstrap/app.php:22`); no DB-touching health route.

## 3. Brand
- `firms`: name, slug (60, unique), logo_text, brand_primary, brand_accent, address, phone, mailbox,
  business_hours json, the partner money model columns
  (`api.rnc/database/migrations/0000_00_00_000001_create_firms_table.php:21-37`). No short name,
  tagline, proof, logo file, modules.
- `firm_brands` = sub-brands of ONE firm ("looks of the one Franco firm, not firms"): slug unique per
  firm, name, short_name, tagline, colours, proof json (4 points), active
  (`.../2026_11_07_300001_create_firm_brands_table.php:7-30`). `PublicFirmResource::underBrand()`
  overrides the look, never contact data (Firm RULES "Sub-brands").
- Contrast rule: white on primary ≥ 4.5:1, `Helpers/Contrast.php`, 422 `brand.lowContrast`.
- Runtime brand exists only on public pages: `useBrandTheme` paints `--color-primary-*` on `<html>`
  and keeps the last palette in `sessionStorage` to avoid a flash; "the back office stays Franco's"
  (`app.rnc/src/modules/Site/composables/useBrandTheme.ts:8-20`). The back office theme is build-time:
  `VITE_ACTIVE_THEME || 'franco'` (`app.rnc/vite.config.ts:26`); theme files `franco.css`,
  `battoni-dev.css`, `_template.css` (`app.rnc/src/styles/theme/themes/`).
- Mail brand is global: `config('rnc.brand')` name "Franco Contabilidade", accent, ink, logo
  (`api.rnc/config/rnc.php:39-63`), read by `AppServiceProvider.php:120-124`, the mail layout,
  `MeetingMailBuilder`, `ReferralService`, `DailyBriefingMail`, `MailPreviewController`.
- The sub-brands console `/operador/marcas` (+ `/operator/brands`) behind `brands.manage`, operator alone (rnc-3 D3).

## 4. Consent
- `ConsentTerms`: `VERSION = 1`, `TEXT` names "Franco" and "Rede de Especialistas Franco";
  `BRAND_TEXT` substitutes a sub-brand name; `CONTRACT_TEXT` likewise
  (`api.rnc/app/Modules/Lead/Helpers/ConsentTerms.php:16-41`).
- `lead_consents` already stores `text` + `version` + `given` + `via` + `given_at` per answer
  (`.../2026_11_07_000005_create_lead_consents_table.php`): the snapshot exists; only the source of
  the text is a constant.

## 5. Roles, routes, names
- Roles (global, not per firm): `operador` (every key, `RoleService::ADMINISTRATOR_SLUG`), `ceo`
  ("Reinaldo"), `equipe`, `parceiro` (`api.rnc/database/seeders/RoleSeeder.php`). Keys declared in
  `app/Modules/Auth/Helpers/permission-groups.local.php`.
- SPA: 12 routes under `/reinaldo/*` (en `/ceo/*`), `/operador/*` (en `/operator/*`), `/equipe/*`
  (en `/team/*`); rail in `app.rnc/src/components/unique/TheNavbar/navItems.local.ts` (groups by
  `WORKSPACES`, `app.rnc/src/modules/Home/constants.ts:13` key `ceo`); labels
  `navigation.ceo = "Reinaldo"` (`app.rnc/src/locales/local/pt-BR.json:23,36`).
- Name count outside tests/docs (comments included): app.rnc/src 206 lines in 72 files; api.rnc app 66
  lines/42 files, config 4, routes 10 (comments), lang 2, resources 1
  (`/usr/bin/grep -rnE "Franco|Reinaldo|Willy|APW"` minus `*.spec.ts`, `*.md`, `/test/`, `*Test.php`).
- Seeded people: `battoni` (operador, firm franco — `SuperAdminSeeder.php:30-42`), `reinaldo` (ceo),
  `patricia` (equipe), four specialists `mariana.toledo`, `carlos.prado`, `juliana.reis`,
  `rafael.antunes` (`LocalUsersSeeder.php:20-25`), `parceiro.1`…`parceiro.8`
  (`PartnerUserSeeder.php`); passwords `config('seeding.password')` / `superadmin_password`.
  `FirmSeeder` holds Franco's real public contact (lines 20-29) — must never reach a prospect's demo.

## 6. Module couplings (what must degrade)
- Briefing reads Booking, Lead, Partner (`Referral`, `ReferralSla`), Radar (`RadarService`) and
  Portfolio (`Client`) (`api.rnc/app/Modules/Briefing/Services/BriefingService.php:5-17`,
  `Repositories/BriefingRepository.php:6-13`).
- Radar reads Diagnostic, Fiscal (`SalesTotals`), Partner (`ReferralService`) and Portfolio
  (`api.rnc/app/Modules/Radar/Services/RadarService.php:6-12`).
- Contracts live in Portfolio (`ContractService`); its legal referral is Partner's
  (`ContractReferralController`, rnc-3 F6b).
- The chat receptionist is a channel of the public diagnostic (`LeadChannel::Chat`,
  `api.rnc/app/Modules/Lead/Enums/LeadChannel.php`), not its own route group.
- Scheduled: `appointments:remind` hourly, `assistant:notify-expiring` and `briefing:send` 07:00
  São Paulo (`api.rnc/routes/console.php:13-21`). Queued mailables: 5 classes implement `ShouldQueue`.

## 7. Business context
- The hub was priced to Franco by hours (507 h whole menu, R$ 185/h) — `battonidev-1` `commercial.md` §3;
  the white-label subscription and the beta (~2 firms, ~3 months, promo price) are the owner's
  13/11/2026 decisions (coordinator's brief), not yet in `commercial.md`.

## Could not read / not verified
- Whether a local MySQL login works (F7 tries it).
- Sanctum's wildcard matching of `SANCTUM_STATEFUL_DOMAINS` (`*.localhost:5173`) — from memory of
  Sanctum's `Str::is` check, to be proven by F1b's live check.
