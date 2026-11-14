---
version: 1.8.0
origin: rnc
---
# RULES — Firm Module

> For AI agents. rnc-1 F1, 07/11/2026.

## Domain

The tenants (D1): an accounting firm whose hub this is. Franco Contabilidade is firm 1
(`FirmSeeder`). A firm also has **sub-brands** (`firm_brands`, rnc-3 R1): Franco Express and
Franco Prime are looks of the one Franco firm, not firms.

*Retired 07/11/2026 (rnc-3 F1):* "the RNC firms and the Express/Prime sub-brands of Phase 3 are
more rows" of `firms`. rnc-3 D1 made the sub-brands `firm_brands` rows — they share Franco's data,
network, address and mailbox, and a second firm would split the pipeline — and D5 made the RNC
firms records of the Channel module, with no tenant.

## How a query is scoped

- **`CurrentFirm`** (a scoped container binding) holds the firm of this request or command run.
- **`ResolveCurrentFirm`** (api middleware, appended after `EnsureAccountActive`) sets it (rnc-4 F1a):
  - signed in with a firm → that firm; an `X-Firm` naming another → 403 `firms.mismatch` (D5);
  - signed in with NO firm (the operator) → `ViewAsFirm` (F4c; null until then), else the platform
    firm; any `X-Firm` → 403 `firms.mismatch`; no platform firm → 403 `firms.required` (plan AM1);
  - a visitor → the `X-Firm` slug through `FirmResolver::findTenantBySlug` (a reserved slug of
    `config/platform.php`, an unknown one or the platform firm's → 404 `firms.notFound`, D2, AM9),
    without the header `config('firms.default_slug')`: `hub` in the app, `franco` in `phpunit.xml` (D6).
  *Retired 13/11/2026 (rnc-4 F1a):* "the signed-in person's `firm_id` wins; the default is `franco`".
  A firm-less session fell through to the header — a way into any firm — and a null firm answered
  500 further on; Franco is now one tenant among many, and the root is the platform's.
- **`FirmResolver`** is the one place a door asks "which firm does this address name" (header,
  host for the QA login-as) and "does this account belong to it" (`belongsTo`: its own firm, or the
  platform firm for the firm-less `operador`). The logins and the QA login-as use it (Auth RULES).
- **The platform firm** (`is_platform`, slug `hub`, D3) is seeded in every environment by
  `PlatformFirmSeeder`; `FirmRepository::findPlatformFirm()` reads it. `DemoFirmSeeder` (Alvorada,
  D18) is local only (AM7).
- **`BelongsToFirm`** (trait) adds `FirmScope` and stamps `firm_id` on create. Every business model
  of later phases uses it. A row of another firm is absent, so the API answers 404, never 403.
- **No firm set → no rows.** `FirmScope` closes to an empty `whereIn` (portable, AM11).
- **Commands** walk `CurrentFirm::forEachFirm()`; a queued job that received the model reads it
  with `withoutGlobalScope(FirmScope::class)` and nothing else does.
- **A lookup keyed by a visitor's input names the firm itself** (rnc-3 review A1):
  `BrandRepository::findActiveBrandBySlug` (`X-Brand`) filters `firm_id` by `CurrentFirm` beside the
  scope, so the guarantee holds even if the scope is lifted; no current firm finds nothing.

## Public endpoint

- **`GET /api/public/firm`** (`PublicFirmController`, rnc-1 F2) answers the request's firm — already
  chosen by `ResolveCurrentFirm` — through `PublicFirmResource`, an allow-list: name, slug, brand,
  address, phone, `phone_href` (`tel:+55…`, so the site never parses a phone), mailbox, hours. No id,
  no timestamps. No firm (an empty database) is a 404.

## The firm's own brand (rnc-4 F2a, R8, R9, R12)

- **`firms`** carries the look beside `logo_text`: `short_name` (40), `tagline` (160, null keeps the
  site's own line), `proof` (four `{value, label}`, null keeps the site's own), `logo_path` (a file on
  the `public` disk — uploaded in F4c) and `modules` (opt-in keys, D15; json with no database default,
  the model's `$attributes` gives `[]`).
- **`GET public/firm`** answers them for the main brand, plus `logo_url` (the `public` disk's URL,
  null without a file and under a sub-brand), `is_platform` and `platform: {name}` —
  `config('platform.name')` (`PLATFORM_NAME`), the one place the product is named (R9). `GET auth/me`
  carries the same `platform.name` (`UserResource::withPlatform`).
- **`MailBrand`** (Services) is the brand an e-mail wears: name, ink (primary), accent, logo, the
  firm's mailbox and its own site (`<slug>.<host>` of `rnc.frontend_url`, the root for the platform
  firm). Built from a firm (and a sub-brand) where the mail is queued, carried in the DTO (AM6);
  `sender()` is `mail.from.address` under the firm's name, `replyTo()` the firm's mailbox (D11).
- **`PlatformSettings`** (Services) is the only writer of `platform_settings` (key/value, no firm):
  `consent_default`, the sentence a new firm's consent version 1 copies (Lead's `ConsentTextService`,
  R13). `PlatformFirmSeeder` writes it in every environment; an unset key reads the built-in default.

## Sub-brands (rnc-3 F1, R1–R5)

- **`FirmBrand`** (`BelongsToFirm`): slug (unique per firm), name, short name, tagline, primary and
  accent, `proof` (exactly four `{value ≤ 20, label ≤ 60}`), active. The main brand is the firm's
  own row; there is no `firm_brands` row for it. Never deleted, only deactivated, so its leads keep
  their chip. `firm_id` and `slug` are not fillable: an edit moves neither.
- **`CurrentBrand`** (scoped binding) holds the public request's sub-brand, null = main.
  **`ResolveCurrentBrand`** (route alias `brand`, on every `public/*` route and nowhere else) reads
  `X-Brand` and looks the slug up **through `FirmScope`, under the firm `ResolveCurrentFirm` already
  set** — so a header can only name a brand of the request's own firm; another firm's slug is as
  unknown as a typo. Unknown or inactive → 404 `brands.notFound` (the SPA reads it to drop the stored
  brand), except under `brand:fallback` (`POST public/events`, AM21), which records on the main brand.
- **A brand changes the look, never who answers.** `PublicFirmResource::underBrand()` overrides name,
  logo text, short name, tagline, colours and proof; address, phone, mailbox and hours stay the
  firm's. The main brand answers `brand`, `short_name`, `tagline` and `proof` as null.
- **Contrast (D4):** white on the primary must reach 4,5:1 (`Helpers/Contrast.php`, WCAG 2,
  pure), else 422 `brand.lowContrast`; a colour that is not `#rrggbb` is 422 `brands.invalidColour`.
  Colours are stored lower-case.
- **The console** (`BrandController`, `brands.manage`, the operator's alone — D3): `GET brands`
  answers `{main, brands}` with each lead count from `LeadRepository::countLeadsByBrand()`, one
  grouped query whatever the number of brands; `PATCH brands/{id}`, `POST brands/{id}/activate|deactivate`.
- **The lead's brand is the Lead module's** (`leads.brand_id`, no foreign key across modules); this
  module answers the board filter's slug → id (`BrandService::brandIdForFilter`, `main` → `Lead::MAIN_BRAND`).
## The network's money model (rnc-2 F4, R11, D10)

- **One model per firm**: `firms.partner_money_model` (`PartnerMoneyModel`: `comissao` · `assinatura`,
  a string, no enum column — AM11), `partner_commission_rate_bp` (1000 = 10 %) and
  `partner_monthly_fee_cents` (49000 = R$ 490), folded into the `create_firms` migration (D15); the
  model's `$attributes` carry the same defaults so a firm just created reads them.
- **`FirmSettingsService` is the only writer** of these columns, and writes the CURRENT firm only
  (`CurrentFirm` — the signed-in person's, never `X-Firm`). Partner's `PATCH ledger/model` calls it;
  Partner's `LedgerService` reads them through `CurrentFirm`. The rate and the fee have no write
  path yet: they change in the seeder or the database (Known Gaps).
- `PublicFirmResource` is an allow-list, so none of this reaches the public site.

## Modules per firm (rnc-4 F3a, R16–R20, D15, D16)

- **`Helpers/FirmModules`** is the catalogue, pure: `CORE` (code, always on, never stored), `OPT_IN`
  (`portfolio`, `fiscal`, `radar`, `partners`, `contracts`, `chat`, `assistant`, `briefing`, `brands`),
  `OPERATOR_ONLY` (`channel`), `ALL` (the stored order) and `DEPENDENCIES` (`radar`, `contracts`,
  `fiscal` → `portfolio`). `firms.modules` holds the opt-in keys; a key the catalogue no longer knows
  reads as off (`enabled()`).
- **`FirmModulesService::set($firm, $modules, $actor)` is the only writer**: core keys dropped, an
  unknown key 422 `modules.unknown`, a missing dependency 422 `modules.missingDependency`, `channel`
  turned on or off by anyone but the operator 403 (leaving it as it was is anyone's). No route calls
  it yet: the console (F4a) and `firm:create` (F3b1) will.
- **`CurrentFirm::hasModule()`** is what a service asks before reading another module (Briefing,
  Radar, Contracts degrade, R19); **`forEachFirmWithModule()`** is what a scheduled command walks
  (`briefing:send`, `assistant:notify-expiring`, R17).
- **`EnsureModuleEnabled`** (route alias `module`, `module:<key>[,<key>…]`, every key needed) answers
  404 `modules.disabled` when the request's firm has a module off — LAST on the route, after `auth`
  and its key. `ResolveCurrentBrand` knows no sub-brand with `brands` off.
- **`GET auth/me`** carries `firm` (`Resources/FirmSummaryResource`: slug, name, short name, logo text
  and URL, colours, `is_platform`, `modules` — the opt-in keys only) or null for a firm-less account,
  and `platform: {name, is_operator, view_as}` (Auth's `UserResource::withPlatform`).
- **Seeds:** Franco every key (`channel`, `brands` included), Alvorada every opt-in key but `brands`
  (D18), the platform firm none (R23). `FirmFactory` defaults to every key so the suite's implicit
  firm keeps its routes; the model's own default is none.

## A new firm (rnc-4 F3b1, R21, D17)

- **`FirmProvisioner`** is the one way a firm is made — `php artisan firm:create <slug> --name=
  --manager= [--email=] [--specialist]` today, the platform console (F4a) next. One transaction: the
  firm (default `#1f4e79` / `#f2a900`, weekdays 09–18, name as logo text and short name, the e-mail as
  mailbox), core + `briefing` through `FirmModulesService::set` (the actor is the console's operator,
  else the new manager), then as the firm (`runAs`): the six default sector pages
  (`SectorPageService::startFirm`), consent v1 (`ConsentTextService::startFirm`), the `gestao` manager
  (`UserService::create`) and, with `--specialist`, their hours (`SpecialistService::makeBookable`).
- **The password is 16 letters and digits, returned once** (`ProvisionedFirm`) and printed by the
  command; nothing keeps it but the hash.
- **Refusals come all at once** as a `ValidationException` (the console's 422): `firms.slugInvalid`
  (not a DNS label within 60), `firms.slugReserved` (`config/platform.php`), `firms.slugTaken`,
  `firms.nameRequired`, `users.form.usernameRequired` / `usernameTaken`, `users.form.emailInvalid` /
  `emailTaken` — checked before anything is written.
- **Reads from other modules:** `roles` (Auth's `RoleRepository::findRoleBySlug`), `users` (through
  `UserService::isUsernameAvailable` / `isEmailAvailable`). Every write goes through the owning
  module's service.

## Intentional Decisions

- **`PublicFirmResource` answers `modules`** (rnc-4 F3c, R17, W47): the firm's opt-in keys as `auth/me` lists them (core never listed; the platform firm none), the firm's under a sub-brand too. The public site reads it to hide the chat where `chat` is off.
- **`users` is not firm-scoped.** Sign-in looks the username up before any firm is known; the user's
  `firm_id` is what scopes everything they read. The seeded `operador` (`battoni`) still belongs
  to Franco until rnc-4 F4a moves it to no firm (platform firm, root only).
- **The header never moves a signed-in person — and a mismatch is refused, not ignored** (rnc-4
  D5). Otherwise `X-Firm` would be a way into another firm's leads; a 403 tells the SPA its session
  is on the wrong host, and it signs out. Only a header that is PRESENT is compared, so the suite's
  firm-B users with no header keep their 404s.
- **`users` reads go through the firm** (rnc-4 AM2): `UserRepository` filters the list, the support
  names, the phone lookup and `auth/users/{id}` by the current firm; username and e-mail stay unique
  across all firms.

## Known Gaps

- **Until F4c the operator reaches another firm only through the console** (rnc-4 AM1): a
  firm-less session always works in the platform firm; `ViewAsFirm` returns null.
- **`FirmProvisioner`'s refusal keys have no SPA text yet** (rnc-4 F3b1): `firm:create` prints the
  keys themselves; the console (F4a) adds `firms.slug*`, `firms.nameRequired` and the new
  `users.form.*` keys to the locales. Two runs racing for one slug: the loser's unique index is an
  uncaught 500 — the CLI is one operator at a time; F4a's console should catch it as `slugTaken`.

- **Modules are enforced by the API only until F3c** (rnc-4): the SPA still shows every rail entry
  and the root site's chat; on a firm with the module off they answer 404 / 422 `modules.disabled`.
  The platform firm (`hub`) has no `chat`, so the root diagnostic's chat is refused until F3c hides it.
- **Host mapping (rnc-3 D2).** A brand is chosen by `?marca=` + `X-Brand` because locally there is
  one host. `express.franco…` hosts need a `host` column read by `ResolveCurrentBrand`.
- **No brand is created or renamed through the API.** Express and Prime are `BrandSeeder` rows; a
  third brand is a seeder row until a screen asks for one.
- **The commission rate and the monthly fee have no screen or endpoint** (rnc-2 F4): R11 asks only
  for the model switch; a different rate or fee is a database edit until Franco asks for one.

- **The first models on `BelongsToFirm` are F2's** `SectorPage`, `Visitor` and `TrackingEvent`; the
  scope itself is proven on a test-only table (`tests/Feature/Firm/FirmScopeTest.php`, A1). (This
  line used to say F3's `Lead` would be the first and kept a `trait.unused` PHPStan entry until then;
  F2 deleted the entry.)
- **No firm admin screen.** Firms are seeded; a second firm is a seeder row until Phase 3.
