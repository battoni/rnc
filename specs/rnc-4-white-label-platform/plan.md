# Plan: Reforma hub as a white-label platform for accounting firms
ID: rnc-4 · Spec: `specs/rnc-4-white-label-platform/spec.md` (closed 13/11/2026)
Approved: Guilherme Battoni, 13/11/2026 (delegated in away mode: "Go ahead and make it happen")

Every phase: tests red first (Pest for the API, then Vitest for the SPA, then the phase's e2e files),
then code, then green, then the phase gate (Defaults → Gate), then `state.md`, then ONE commit
`rnc-4: F<label> <what>` carrying code and state. API code follows
`.claude/rules/arcus-api-architecture.md` (route → FormRequest → DTO → Service → Repository →
Resource → `ApiResponse`; module RULES.md + CHANGELOG.md bumped); routes in `routes/api.local.php`;
keys in `app/Modules/Auth/Helpers/permission-groups.local.php`, granted in `RoleSeeder`
(`SeededRolesTest` updated); every new route classified in `tests/Feature/Access/routeClasses.php`.
SPA code follows `.claude/rules/celer-0*.md` (module barrels `@XModule`, two routes per screen
pt-BR + en, rail in `navItems.local.ts`, `MAddEdit{Entity}Form` in `MMainDialog`, granular list
updates, `canX` computeds, strings in `src/locales/local/{pt-BR,en}.json`). A public call goes
through `publicApi`, never `API` (rnc-3). Node 24 for app.rnc. Phases run in the order listed
(the owner's priority, O9): a quota stop leaves the most valuable part done.

## Carried up front
- **P1 Hosts.** Never `127.0.0.1`. From F1b on, local URLs are `http://localhost:5173` (platform
  firm), `http://franco.localhost:5173`, `http://alvorada.localhost:5173`; the API at
  `http://localhost:8000` is reached by the SPA only through Vite's `/api` proxy (D4). Live checks
  in this worktree use `make serve-api`, `make serve-app`, `make fresh-seed` (SQLite), the main
  checkout's stack stopped. `MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`.
- **P2 Folded migrations (D8).** Columns go into the existing `create_*` files; after any migration
  edit, `make fresh-seed`. No `add_*` file in this feature.
- **P3 Existing tests keep their implicit firm** through `DEFAULT_FIRM_SLUG=franco` in
  `phpunit.xml` (D6). A test that needs the platform firm or alvorada creates it.
- **P4 Isolation tests are written as firm-B tests**: every new firm-scoped endpoint gets a "firm B
  → 404" and a "wrong role → 403" line in its feature test (R37).
- **P5 Red means red.** Each new test is seen failing before its code, and at least one mutation per
  rule turns it red (shared conventions). Log the mutations in the phase's Done line.
- **P6 Security-sensitive phases (F1a, F4a, F4c) are flagged for the verify review**: list in the
  Done line every place a firm is chosen, a login is refused, or a file is accepted.

## Phases

**F1a Firm by host — API** (R1–R5, R7 · proves A1–A5)
- Firm: `config/platform.php` (new: `name`, `root_domain`, `reserved_slugs`); `config/firms.php`
  (changed: default comment, `DEFAULT_FIRM_SLUG`); `create_firms` migration (changed: `is_platform`
  bool default false — the rest of R8's columns come in F2a); `Repositories/FirmRepository.php`
  (changed: `findPlatformFirm()`); `Services/CurrentFirm.php` (unchanged API, doc).
- `app/Http/Middleware/ResolveCurrentFirm.php` (changed): signed-in with a firm → that firm, and if
  `X-Firm` is present and names another firm → 403 `firms.mismatch` (D5); signed-in with NO firm →
  nothing set (view-as hook left for F4c: a `ViewAsFirm` resolver returning null for now); guest →
  header, else default; header slug reserved/unknown → 404 `firms.notFound`.
- Auth: `Services/AuthService.php` + the phone-code verify service (changed): after the password
  check, `user.firm_id` must equal `CurrentFirm::id()`; a firm-less `operador` only when the current
  firm `is_platform`; otherwise the invalid-credentials error with the timing path unchanged.
  `routes/api.php` (changed: register/email-code routes only when `config('registration.enabled')`,
  default false — D21) and `config/registration.php` (changed).
- Seeds: `database/seeders/PlatformFirmSeeder.php` (new: `hub`, `is_platform`, name =
  `config('platform.name')`, every environment), `DemoFirmSeeder.php` (new, minimal for now:
  alvorada row with invented contact and palette D18, one manager `helena.alvorada` (gestão role
  is still `ceo` until F2c), one specialist `bruno.alvorada` with hours — local only),
  `DatabaseSeeder.php` / `seeders.local.php` (changed). `.env.example` (changed:
  `DEFAULT_FIRM_SLUG=hub`, `PLATFORM_NAME`, `PLATFORM_ROOT_DOMAIN`, `REGISTRATION_ENABLED`,
  `SANCTUM_STATEFUL_DOMAINS=localhost:5173,*.localhost:5173`); `phpunit.xml` (changed:
  `DEFAULT_FIRM_SLUG=franco`).
- Tests first: `tests/Feature/Firm/FirmByHostTest.php` (A1, A2), `tests/Feature/Auth/LoginFirmTest.php`
  (A3: password, phone code, partner login), `tests/Feature/Firm/FirmMismatchTest.php` (A4),
  `tests/Feature/Auth/RegistrationClosedTest.php` (A5); existing registration tests moved under
  `config(['registration.enabled' => true])`.
- Done when: the touched Pest dirs (Firm, Auth, Access, Lead, Diagnostic) are green; Firm and Auth
  RULES.md updated (Intentional Decisions: "the header never moves a signed-in person" now also
  refuses a mismatch).

**F1b Firm by host — SPA and local hosts** (R2, R3, R6 · proves A6, A7)
- `app.rnc/src/helpers/firmHost.helper.ts` (new: `firmSlugFromHost(host, rootDomain)` → slug |
  null for root/`www`; `VITE_ROOT_DOMAIN`), `src/providers/ApiProvider/provider.ts` (changed: adds
  `X-Firm` on every call when a slug exists; base URL `/api`), `errorHandler.helper.ts` (changed:
  403 `firms.mismatch` → clear the user store and go to login), `src/constants/env.constant.ts`.
- `vite.config.ts` (changed: `server.proxy['/api'] → http://localhost:8000` keeping the Host
  header, `server.allowedHosts: ['.localhost']`, same for `preview`); `.env.example`, `.env.test`
  (changed: `VITE_API_URL=/api`, `VITE_ROOT_DOMAIN=localhost`); `playwright.config.ts` (changed:
  `baseURL` `http://franco.localhost:<port>` — D31; check every e2e route intercept still matches
  `**/api/**`).
- `src/views/FirmNotFound/FirmNotFoundView.vue` (new, `TheCenteredLayout`; "Escritório não
  encontrado", "Confira o endereço ou volte para a página inicial", link to the root) shown when
  the boot `public/firm` answers 404 `firms.notFound`.
- Tests first: `firmHost.helper.spec.ts`, `provider.spec.ts` (A6 header + base URL),
  `FirmNotFoundView.spec.ts`; `e2e/tenancy/firm-host.spec.ts` (A7: intercepts assert `X-Firm:
  franco`; a mocked 403 `firms.mismatch` lands on login).
- Done when: Vitest of providers/helpers/views + `make e2e E2E_ARGS=e2e/tenancy/firm-host.spec.ts`
  green, plus a 5-test e2e smoke (`e2e/site`) to prove the base URL move; **live**: on a fresh seed,
  `patricia` signs in on `franco.localhost:5173` and is refused on `alvorada.localhost:5173`; a
  diagnostic started on alvorada's host shows on `helena.alvorada`'s board and not on patricia's.

**F2a Firm brand, product name, mail brand, consent versions — API** (R8, R9, R12, R13 · proves A8–A10)
- `create_firms` (changed: `short_name` 40, `tagline` 160 nullable, `proof` json nullable,
  `logo_path` nullable, `modules` json default `[]`); `Firm` model (casts, fillable);
  `PublicFirmResource` (changed: + `short_name`, `tagline`, `proof`, `logo_url`, `is_platform`,
  `platform: {name}`; `underBrand()` unchanged); `FirmSeeder`/`DemoFirmSeeder`/`PlatformFirmSeeder`
  (changed: the new columns).
- Mail: `app/Modules/Firm/Services/MailBrand.php` (new: name, ink = primary, accent, logo path from
  a firm); `AppServiceProvider.php:115-125`, `resources/views/mail/layout.blade.php`,
  `MeetingMailBuilder`, `ReferralService` mail, `DailyBriefingMail`, `MailPreviewController`
  (changed: read the firm's `MailBrand`; queued mailables carry the firm id and resolve it with
  `withoutGlobalScope`); from-name + reply-to per firm (D11); `config/rnc.php` `brand` removed;
  `resources/views/mail/RULES.md` updated.
- Consent: `create_firm_consent_versions` + `create_platform_settings` (new, `300100`-series names),
  `Lead/Models/FirmConsentVersion.php` (BelongsToFirm), `Lead/Services/ConsentTextService.php`
  (current version, `{escritorio}` fill, new version), `Firm/Services/PlatformSettings.php`
  (`consent_default`); `ConsentTerms` (changed: constants removed except `VIA_*`; generic contract
  sentence with `{escritorio}` — D12); the diagnostic start and the chat path store the service's
  version + text. Seeds: Franco v1 = today's sentence (seeder only), alvorada and hub v1 = the
  platform default ("Autorizo {escritorio} a me apresentar a especialistas parceiros quando o
  diagnóstico indicar.").
- Tests first: `tests/Feature/Firm/PublicFirmBrandTest.php` (A8), `tests/Feature/Mail/FirmMailBrandTest.php`
  (A9 meeting, referral, briefing), `tests/Feature/Lead/ConsentVersionTest.php` (A10);
  `MailSenderTest` adjusted.
- Done when: Pest dirs Firm, Lead, Diagnostic, Booking, Partner, Briefing, Mail green.

**F2b Runtime brand in both layouts — SPA** (R9, R10, R11 · proves A11, A12)
- `src/composables/brand/` (new: `useFirmBrand.ts` — boot fetch of `public/firm` once, store of the
  firm + `platform.name`; moved `brandPalette.ts`, `useBrandTheme.ts` (now mounted by `App.vue`
  for both layouts, sub-brand override only while a public layout is mounted), `publicBrand.ts`);
  `src/modules/Site/composables/index.ts` (re-exports, Site RULES.md: the move); `ThePublicLayout.vue`,
  `TheLayout.vue` + `TheNavbar` logo text (changed); `src/styles/theme/themes/hub.css` (new neutral
  theme, from `franco.css` values), `vite.config.ts` default `hub`, `.env.example`/`.env.test`
  (`VITE_ACTIVE_THEME=hub`); `index.html` neutral title; `document.title` from `platform.name`.
- Tests first: `useFirmBrand.spec.ts`, `useBrandTheme.spec.ts` (moved + both layouts, A11),
  `TheLayout.spec.ts` (logo text); `e2e/brand/two-palettes.spec.ts` (A12: franco and alvorada mocked
  `public/firm`, light/dark, landing + diagnostic + home + pipeline, the existing contrast helper).
- Done when: Vitest of composables/layouts/Site + the new e2e + existing `e2e/brands` green.

**F2c De-Franco rename — one mechanical commit, then the guard** (R14, R15 · proves A13–A16) — FULL GATE
- Mechanical (no logic): SPA route paths `/reinaldo/*` → `/gestao/*`, `/ceo/*` → `/management/*`,
  route names `ceo-*` → `management-*`, `WORKSPACES` key `ceo` → `management`, locale keys
  `navigation.ceo`/`home.workspaces.ceo` → `management` with "Gestão"/"Management"; API role slug
  `ceo` → `gestao` ("Gestão", description without a name) in `RoleSeeder`, `LocalUsersSeeder`,
  `DemoFirmSeeder`, Briefing's recipients query and every test that names the slug; strings
  naming Franco/Reinaldo in locales, resources, lang and code → generic or `{firm}` (the brand
  composable passes it; API messages take the firm's name). Comments may keep history.
- Guard: `tests/Arch/NoTenantNamesTest.php` (new, A15 Pest half) and
  `app.rnc/src/test/noTenantNames.spec.ts` (new, A15 Vitest half; extends rnc-3's
  `noPartnerNetworkNames` idea); `SeededRolesTest` (A14); `routes.spec.ts` of Briefing/Home (A13);
  `e2e/brand/no-franco.spec.ts` (A16, alvorada mocked).
- Commit split allowed here: `rnc-4: F2c Rename …` (mechanical) is the phase commit; if the guard
  needs a second commit, name it `rnc-4: F2c Guard …` and record both SHAs in state.
- Done when: **full gate** — every Pest, Vitest and Playwright test green (Defaults → Gate);
  `/usr/bin/grep -rnE "Franco|Reinaldo|Willy|APW" api.rnc/app api.rnc/config api.rnc/routes
  api.rnc/lang api.rnc/resources app.rnc/src` minus comments/tests/seeders shows nothing.

**F3a Modules per firm — API with degrade** (R16–R20 · proves A17–A21)
- Firm: `Helpers/FirmModules.php` (new: `CORE`, `OPT_IN`, `FRANCO_ONLY`, `DEPENDENCIES`),
  `Services/FirmModulesService.php` (new: set with R18 checks; `channel` operator-only),
  `app/Http/Middleware/EnsureModuleEnabled.php` (new, alias `module`, 404 when off);
  `routes/api.local.php` (changed: `module:partners` on partners/referrals/portal/ledger,
  `module:portfolio` on repricing/clients, `module:fiscal`, `module:radar`, `module:contracts`,
  `module:assistant`, `module:briefing`, `module:brands`, `module:channel`); the diagnostic's chat
  channel refused with 422 `modules.disabled` when `chat` is off.
- Degrade: `BriefingService`/`BriefingRepository`/`ActionPicker` (sections and actions null by
  module), `RadarService` (no evidence without `fiscal`, no batch referral without `partners`),
  `ContractService` (no referral fields without `partners`); commands `briefing:send`,
  `assistant:notify-expiring` skip firms without the module.
- `AuthController::me` / `UserResource` (changed: `firm` summary + `platform`, R20).
- Seeds: Franco gets every module incl. `channel` and `brands`; alvorada D18's set; hub none.
- Tests first: `tests/Feature/Firm/ModuleGateTest.php` (A17), `tests/Feature/Firm/ModuleCommandsTest.php`
  (A18), `tests/Unit/Modules/Firm/FirmModulesServiceTest.php` (A19),
  `tests/Feature/Briefing/BriefingDegradeTest.php` + `tests/Feature/Portfolio/ContractDegradeTest.php`
  (A20), `tests/Feature/Auth/MeFirmTest.php` (A21).
- Done when: Pest dirs Firm, Auth, Briefing, Radar, Portfolio, Partner, Assistant, Channel, Access green.

**F3b `firm:create` and the demo firm** (R21–R23 · proves A22, A23)
- `app/Console/Commands/CreateFirm.php` (new) over `Firm/Services/FirmProvisioner.php` (new: firm,
  modules, sector pages from `SectorPageSeeder`'s default set made reusable, consent v1 from
  `PlatformSettings`, manager, specialist hours) — the console (F4a) reuses the provisioner.
- Seeders made firm-parameterised (`run()` loops the seeded firms through
  `CurrentFirm::runAs`, invented names per firm): `PipelineSeeder`, `PartnerSeeder`,
  `PartnerUserSeeder` (usernames `parceiro.<slug>.<n>` for non-Franco firms), `MetricsSeeder`,
  `ClientSeeder`, `ContractSeeder`, `NfeSeeder`, `AssistantNoteSeeder`, `SpecialistSeeder`;
  Franco-only stay Franco-only (`ChannelSeeder`, `BrandSeeder`). `DemoFirmSeeder` (changed: full
  team — manager, `equipe.alvorada`, specialists `bruno.alvorada`, `camila.alvorada` — and the
  data); hub's manager `gestao.hub` (local only).
- Tests first: `tests/Feature/Firm/CreateFirmCommandTest.php` (A22), `tests/Feature/Seed/DemoFirmSeedTest.php`
  (A23; `SeedShapeTest` extended).
- Done when: Pest dirs Firm, Seed, Access + the seeders' tests green; `make fresh-seed` passes and
  prints the people; `php artisan firm:create acme …` then the printed password signs in on
  `acme.localhost:5173` (live).

**F3c Modules per firm — SPA** (R17, R19, R20 · proves A24, A25)
- `src/modules/User` store (changed: `firm`, `platform` from `me`); `src/helpers/attachAuthGuard.helper.ts`
  (changed: `meta.module` → not-found view when off); route files of Partner, Repricing, Fiscal,
  Radar, Contracts, Assistant, Briefing, Brands, Channel, Portal (changed: `meta.module`);
  `navItems.local.ts` (changed: an entry's `module` hides it); `BriefingView` + its organisms
  (changed: null sections not rendered); Radar/Contracts views hide the parts R19 names.
- Tests first: `attachAuthGuard.helper.spec.ts`, `navItems.local.spec.ts` (A24),
  `BriefingView.view.integration.spec.ts` (A25), Radar/Contracts view specs.
- Done when: Vitest of the touched modules green; live: alvorada with `partners` off (tinker) shows
  no partner entries and `/gestao/parceiros` is not found.

**F4a Platform operator and console — API** (R11, R24, R25 · proves A26–A28) — security-sensitive
- `SuperAdminSeeder` (changed: `battoni` firm null); `AuthService` (F1a rule already lets a
  firm-less `operador` in at the platform firm only — tested here end to end); keys
  `platform.firms`, `platform.consent` (operator only; `RoleService` protects them).
- New module `api.rnc/app/Modules/Platform/` (RULES.md, CHANGELOG.md): `PlatformFirmController`
  (`GET platform/firms` with lead counts in one grouped query, `POST platform/firms` via
  `FirmProvisioner`, `PATCH platform/firms/{id}` look + contrast, `PUT platform/firms/{id}/modules`
  via `FirmModulesService`, `POST platform/firms/{id}/manager` create/reset → password once),
  `PlatformConsentController` (`GET/PUT platform/consent-default`), requests, DTOs, resources;
  the sub-brands console API (`brands*`) usable by the operator only in view-as (F4c) — unchanged now.
  `FirmScope` lifted only inside the Platform repositories, each lookup by id explicit.
- Tests first: `tests/Feature/Platform/OperatorSignInTest.php` (A26),
  `tests/Feature/Platform/PlatformFirmsTest.php` (A27), `tests/Feature/Platform/ConsentDefaultTest.php`
  (A28); route classes + `Phase4AccessTest.php` (new sweep file).
- Done when: Pest dirs Platform, Firm, Auth, Access green.

**F4b Operator console — SPA** (R25 · proves A29, A30) — FULL GATE
- New SPA module `Platform`: `FirmsView` (`/plataforma/escritorios`, `/platform/firms`: table with
  name, subdomain link, modules chips, created, leads; "Novo escritório"), `MAddEditFirmForm` in
  `MMainDialog` (name, slug with live subdomain preview and reserved/format errors, logo text, short
  name, tagline, proof ×4, colours with the contrast error on the field), `MFirmModulesForm`
  (switches by group core/opt-in/Franco-only, dependency message), `MManagerLoginDialog` (username,
  e-mail → password shown once with copy), `ConsentDefaultView` (`/plataforma/consentimento`);
  rail group "Plataforma" (only `platform.firms`); the operator's home lands on the console when
  `me.firm` is null.
- Tests first: component specs + `FirmsView.view.integration.spec.ts` (A29);
  `e2e/platform/console.spec.ts` (A30).
- Done when: **full gate** green; live: battoni at `localhost:5173` creates "Ipê Contábil" (`ipe`),
  creates its manager, the manager signs in at `ipe.localhost:5173`.

**F6a Beta signup — API** (R28, R29 · proves A31)
- `LeadChannel::Beta` (`beta`); `Lead/Controllers/PublicBetaSignupController.php`,
  `Requests/BetaSignupRequest.php`, `DTOs/BetaSignupDTO.php`, `Services/BetaSignupService.php`
  (only when `CurrentFirm` is the platform firm, else 404; consent via `ConsentTextService`);
  `Notification` kind `beta_signup` (bell + e-mail to hub's `leads.read` users, hub's brand);
  route `POST public/beta-signups` (`throttle:5,1,beta-signup`, `brand`).
- Tests first: `tests/Feature/Lead/BetaSignupTest.php` (A31).
- Done when: Pest dirs Lead, Notification, Access green.

**F6b Product site on the root — SPA** (R28, R29 · proves A32–A34)
- Site module: `views/ProductLanding/ProductLandingView.vue` (new; picked by the public layout when
  `firm.is_platform`), organisms `OProductHero` (headline, CBS countdown via `MCountdown`, CTA),
  `OProductHowItWorks` (diagnostic → booking → pipeline → metrics), `OProductModules` (core vs
  opt-in cards), `OProductBeta` (how the beta works, D23 price line), `OProductFaq`,
  `MBetaSignupForm` (fields of R29, consent checkbox with the API's text, success state), demo link
  (D24, `VITE_DEMO_FIRM_SLUG`); services `betaSignup.service.ts`; Pipeline's channel chip "Beta".
  Copy in pt-BR, product name from `platform.name`; motion with the existing `v-reveal`,
  `motion-*` utilities; no generic-CRM claims.
- Tests first: `ProductLandingView.view.integration.spec.ts` (A32), `MBetaSignupForm.spec.ts` +
  Pipeline chip spec (A33), `e2e/product/landing.spec.ts` on `http://localhost:<port>` (A34).
- Done when: Vitest Site/Pipeline + the e2e green; live: a signup at `localhost:5173` shows on
  `gestao.hub`'s board with the Beta chip and on no other firm's board.

**F5a Firm settings — API** (R30–R33 · proves A35–A38)
- Keys `firm.settings`, `users.read|create|update|archive` granted to `gestao` (roles screen stays
  `permissions.*`, operator only). `Firm/Controllers/FirmSettingsController.php` (`GET/PATCH
  firm/settings`, contact + hours only); Auth `UserRepository`/`UserService` (changed: every query
  filtered by the caller's firm unless platform operator; create forces the caller's firm and
  allows only `equipe`/`gestao`); Booking `SpecialistHoursController` (`GET/PUT
  specialists/{id}/hours`), flagging a specialist seeds firm hours; Lead
  `ConsentVersionController` (`GET firm/consent-versions`, `POST firm/consent-versions`).
- Tests first: `tests/Feature/Firm/FirmSettingsTest.php` (A35), `tests/Feature/Auth/FirmUsersTest.php`
  (A36), `tests/Feature/Booking/SpecialistHoursTest.php` (A37),
  `tests/Feature/Lead/ConsentVersionEndpointTest.php` (A38).
- Done when: Pest dirs Firm, Auth, Booking, Lead, Access green.

**F5b Firm settings — SPA** (R30–R33 · proves A39, A40)
- New SPA module `FirmSettings`: views `/gestao/escritorio` (contact + hours editor),
  `/gestao/equipe` (team list reusing the User module's list/form with role limited and firm
  implicit; reset password shown once), `/gestao/especialistas` (weekly hours per specialist),
  `/gestao/consentimento` (current text, history table version/date/author, edit with preview →
  "Salvar nova versão"); rail children under "Gestão" behind `firm.settings`.
- Tests first: view/component specs (A39); `e2e/firm-settings/settings.spec.ts` (A40).
- Done when: Vitest of FirmSettings/User + the e2e green; live: `helena.alvorada` saves a consent v2,
  a new diagnostic stores v2, an old lead's consent still shows v1.

**F4c Logo upload and view-as — API** (R26, R27 · proves A41, A42) — security-sensitive
- `Platform/Controllers/FirmLogoController.php` (`POST platform/firms/{id}/logo`, `DELETE …/logo`),
  `Requests/UploadLogoRequest.php` (`mimetypes:image/png,image/jpeg,image/webp`, `max:512`,
  `dimensions:max_width=1024,max_height=1024`, plus `finfo` sniff refusing `image/svg+xml` and
  anything not matching), stored as `firms/<id>/logo-<random>.<ext>` on `public`; `logo_url` in
  `PublicFirmResource`; `MailBrand` embeds it.
- View-as: `create_platform_view_as_sessions` (new: operator user id, firm id, started_at, ended_at,
  end_reason), `Platform/Services/ViewAsService.php` (start → session key + row; end; close stale
  on sign-in with `expirou`), the F1a `ViewAsFirm` resolver reads the session key (operator only,
  firm must exist); `POST platform/firms/{id}/view-as`, `DELETE platform/view-as`,
  `GET platform/view-as-log`; `AuthController::logout` closes it (`logout`); `me.platform.view_as`.
- Tests first: `tests/Feature/Platform/FirmLogoTest.php` (A41, fixtures generated in the test),
  `tests/Feature/Platform/ViewAsTest.php` (A42); `Phase4AccessTest` rows.
- Done when: Pest dirs Platform, Auth, Firm, Access green; the Done line lists the security points
  for the verify review (P6).

**F4d Logo and view-as — SPA** (R26, R27 · proves A43)
- Platform module: logo field in `MAddEditFirmForm` (accept png/jpeg/webp, size check, preview),
  "Acessar como suporte" action per firm row, `ViewAsLogView` (`/plataforma/acessos`);
  `TheLayout` banner organism `OViewAsBanner` (fixed, the firm's name, "Sair"); brand composable
  uses `logo_url` in both layouts.
- Tests first: `OViewAsBanner.spec.ts`, form spec (svg refused), layout spec (logo) (A43).
- Done when: Vitest Platform/layouts green; live: battoni enters alvorada, sees the banner and
  alvorada's board, leaves; the log lists the session.

**F7 Deploy readiness — no deploy** (R34–R37 · proves A44–A46)
- `routes/api.local.php` `GET api/health` → `HealthController` (select 1; 503 on failure);
  `docs/deploy/runbook.md` (new: env inventory API + SPA, wildcard DNS/TLS, vhost serving the SPA
  and proxying `/api` per host, `SESSION_DOMAIN=null`, `SANCTUM_STATEFUL_DOMAINS=*.<root>,<root>`,
  queue worker `php artisan queue:work`, scheduler `schedule:run` each minute and its three
  commands, per-firm mail (D11), backups, `firm:create`, "folded migrations end at the first
  deploy: from then on `add_*_to_*`"); MySQL: try `mysql -uroot -e 'select 1'`, if it works run the
  full Pest on a `rnc_test` database and record the count, else W#.
- Tests first: `tests/Feature/HealthTest.php` (A44), `tests/Feature/EnvInventoryTest.php` (A45),
  route-class completeness + `Phase4AccessTest` coverage over every rnc-4 route (A46).
- Done when: Pest green (full Pest, since A46 reads every route).

**F9a, F9b (optional, only if quota remains)** — F9a API: `/public/privacy` data, lead export +
anonymise (`leads.update` + manager), `firm:offboard` (A48, A49); F9b SPA: `/privacidade` page per
firm (A47). Skipped without a W# if the run reaches verify first; listed as owed in the PR.

**Verify** (the run's Step 2): full gate, `fable-reviewer` with spec + `main..HEAD` (flag P6
points), Chrome QA rounds below, draft PR.

## QA
Env: local — API http://localhost:8000 · SPA http://localhost:5173 (platform firm),
http://franco.localhost:5173, http://alvorada.localhost:5173, http://ipe.localhost:5173 (created in
the round) — `make fresh-seed`, `make serve-api`, `make serve-app`; never production data.
Logins (password = `seeding.password`; `qa/login-as/{username}` for the driver):
platform operator → `battoni` (root only) · hub manager → `gestao.hub` · demo manager →
`helena.alvorada` · demo team → `equipe.alvorada`, specialist `bruno.alvorada` · Franco manager →
`reinaldo` (role `gestao`) · Franco team → `patricia` · partner → `parceiro.1` (franco),
`parceiro.alvorada.1`.
Rounds:
1. Visitor on the root: product landing (390, 1280), beta signup → success; `gestao.hub` sees it on
   the board with the Beta chip; `reinaldo` and `helena.alvorada` do not.
2. Visitor on alvorada: diagnostic → result → booking under alvorada's palette and name; report
   and confirmation mail (`laravel.log`) without "Franco".
3. Demo manager: briefing, pipeline, metrics, settings (contact, team login, specialist hours,
   consent v2 with history).
4. Franco tenant: `reinaldo` at `franco.localhost` — briefing, RNC channel, Express/Prime site
   (`?marca=prime`), consent still v1 for old leads.
5. Platform operator: root sign-in, create "Ipê Contábil", modules, manager login, logo upload (PNG ok,
   SVG refused), view-as into alvorada (banner, board), Sair, log row; logout during view-as closes it.
6. Access sweep: `patricia` refused on alvorada's host; a franco session + `X-Firm: alvorada` 403;
   alvorada with `partners` off → partner routes 404 and no rail entries; unknown subdomain page.
Data: every A# Given state is created by the seeds of F1a/F3b (alvorada, hub, Franco) or inside its
test; round 5 creates `ipe` live. No `<Feature>QaSeeder` needed.
Human round: no — the owner reviews the draft PR and the Chrome evidence first (away run); a human
QA doc can be made after with `/battoni-new-qa-session`.

## Defaults
- **Gate (O10, D30).** Per phase: the Pest directories and Vitest folders the phase touches (named in
  its Done line) + static checks (`pint --test`, PHPStan, ESLint, `vue-tsc`) + the phase's own e2e
  files. Full gate (every Pest, Vitest and Playwright test, `/battoni-pre-commit` in full) at the
  end of F2c, F4b and in verify. Baseline before rnc-4: 1851 Pest · 1289 Vitest · 180 Playwright.
  This overrides `.claude/feature-flow.md` "Phase gate" for this feature (owner, 13/11/2026).
- Product name "Hub da Reforma" · platform slug `hub` · root `localhost` · reserved slugs (D2).
- Contrast 4,5:1 · logo PNG/JPEG/WebP ≤ 512 KB ≤ 1024 px · beta signup throttle 5/min.
- New-firm defaults: primary `#1f4e79`, accent `#f2a900`, modules core + `briefing`.
- Demo firm: Alvorada Contabilidade, `alvorada`, primary `#0f5c4d`, accent `#f4b63f`.
- View-as ends on Sair, logout or session end (`expirou` at next sign-in).

## Plan review
`fable-reviewer`, 13/11/2026, on 465924c — P0: 4 · P1: 5 · P2: 2. Verdict: ready to run after AM1–AM9.
Applied verbatim by the coordinator. **These amendments OVERRIDE the spec, the phases and the defaults above.**

**Phase order after the amendments** (the run builds them in this order; commit subjects use these labels):
F1a · F1b · F2a · F2b · F2c1 · F2c2 · F3a · F3b1 · F3b2 · F3c · F4a · F4b · F6a · F6b · F5a · F5b · F4c · F4d · F7 · (F9a · F9b optional) · verify.
**F7b MySQL fix** (added by the coordinator 14/11 after the owner gave the MySQL login; runs after F9b, before verify): schema, seed and the full Pest suite on MySQL 8 (R36, retires W95) — named long indexes, `SchemaIdentifierLengthTest`, MySQL-only fixes, `make test-mysql`, runbook → Database.

Findings in short: P0-1 a firm-less operator gets a null CurrentFirm (`PublicFirmController.php:26`, `BelongsToFirm.php:28-34`, `ReferralSla.php:49` → 500s); P0-2 users are not firm-scoped (`User.php:25`, `UserRepository.php:25-167`, `LoginCodeService.php:62,87`, `routes/api.php:58-63`) and the fix came only in F5a; P0-3 F1b's live diagnostic check needs sector pages and consent that only arrive in F2a/F3b; P0-4 D4's wiring is incomplete (Vite `server` only when `isLocal`, `vite.config.ts:35-56`; live `.env` not edited; Sanctum default stateful list has no `*.localhost:5173`, `config/sanctum.php:21`; `.env.test:8` comment); P1-1 F2c too big (68 API + 154 SPA files); P1-2 F3b is two phases (~1,800 seeder lines); P1-3 F1a/F1b gates too narrow; P1-4 QA login-as must obey the firm rule; P1-5 `hub` reserved slug vs `X-Firm: hub`; P2-1 `/storage` outside `/api`; P2-2 stale session cookie on `localhost`. D5, D6, D21, D27, D31 confirmed.

**AM1 (F1a, overrides "signed-in with NO firm → nothing set").** A signed-in user with `firm_id` null resolves to `findPlatformFirm()`, or to the F4c view-as firm later. `CurrentFirm` is never null for an authenticated request; if no platform firm exists, answer 403 `firms.required`. Test: a firm-less operator calling `public/firm` gets hub, a firm-scoped list returns hub's rows only, and a create stores hub's id. F4a records under Platform `RULES.md` → Known Gaps: "until F4c the operator reaches other firms only through the console."

**AM2 (F1a, moved from F5a).** Scope user reads now: `findAllActiveUsers`, `findSoleUserByPhone` and `findAllUsers` filter by `CurrentFirm::id()`; `findUserById` gains a firm-checked variant used by every `auth/users/{id}` route (another firm's id → 404); username and e-mail uniqueness stay global. Tests in `tests/Feature/Auth/UsersFirmScopeTest.php`: support names, phone code, index, show, update, archive and delete from firm A against a firm-B user. F5a keeps only the grants and the create rules, plus tests that a manager cannot set role `operador`, `firm_id` or `partner_id`, and cannot touch a firm-less user.

**AM3 (F1b).** Replace the live diagnostic check with: `public/firm` on `alvorada.localhost:5173` names Alvorada; `patricia` is refused there and accepted on `franco.localhost:5173`; `helena.alvorada`'s pipeline is empty while patricia's shows Franco's leads. The alvorada diagnostic check moves to F3b2's Done line.

**AM4 (F1a/F1b).** Put `proxy['/api']` in both the local and the test-mode `server` config. Update the worktree's `api.rnc/.env` and `app.rnc/.env` with the same keys as `.env.example`, and say so in `state.md`. Replace the `.env.test` comment with its retirement reason. Add `tests/Feature/Auth/StatefulHostTest.php`: login with `Origin: http://franco.localhost:5173` then `auth/me` returns 200 on the session, and an unlisted origin returns 401. If the wildcard fails, stop the phase as blocked and record a W# rather than switching to CORS.

**AM5 (gates).** F1a's Done requires the full Pest suite. F1b's Done requires the full Vitest and the full Playwright suites.

**AM6 (F2c split into F2c1 and F2c2, each with a FULL GATE).** F2c1: scripted (`sed`/`perl -pi`) rename of role slug, route paths and names, workspace and locale keys, API and SPA together; no string content changes; record the per-pattern file count in the commit body. F2c2: Franco/Reinaldo strings → `{firm}`, plus both guards and `no-franco.spec.ts`. Never open files one by one for the rename.

**AM7 (F3b split into F3b1 and F3b2).** F3b1: `FirmProvisioner` + `firm:create` (A22). F3b2: seeders per firm + `DemoFirmSeeder` (A23) + AM3's moved check. Demo data must not enter any seed path that Pest runs by default; if it does, gate it behind `app()->environment('local')` and test that explicitly.

**AM8 (F1a).** `QaLoginAsController` applies the same firm rule as `AuthService`. QA (below and in `## QA`): the Chrome driver opens `http://<firm-host>:5173/api/qa/login-as/<username>`, never port 8000, and clears cookies for `localhost` first.

**AM9 (F1a).** The platform slug is reachable only header-less: `X-Firm: hub` is a 404. Tests needing the platform firm set `config(['firms.default_slug' => 'hub'])`.

**AM10 (F7).** The runbook's vhost also serves `/storage` per host.
