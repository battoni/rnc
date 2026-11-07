---
version: 1.3.1
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
- **`ResolveCurrentFirm`** (api middleware, appended after `EnsureAccountActive`) sets it: the
  signed-in person's `firm_id` wins; without a session the `X-Firm` header names the firm by slug,
  and without the header it is `config('firms.default_slug')` (`franco`). An unknown slug is a 404.
- **`BelongsToFirm`** (trait) adds `FirmScope` and stamps `firm_id` on create. Every business model
  of later phases uses it. A row of another firm is absent, so the API answers 404, never 403.
- **No firm set → no rows.** `FirmScope` closes to an empty `whereIn` (portable, AM11).
- **Commands** walk `CurrentFirm::forEachFirm()`; a queued job that received the model reads it
  with `withoutGlobalScope(FirmScope::class)` and nothing else does.

## Public endpoint

- **`GET /api/public/firm`** (`PublicFirmController`, rnc-1 F2) answers the request's firm — already
  chosen by `ResolveCurrentFirm` — through `PublicFirmResource`, an allow-list: name, slug, brand,
  address, phone, `phone_href` (`tel:+55…`, so the site never parses a phone), mailbox, hours. No id,
  no timestamps. No firm (an empty database) is a 404.

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

## Intentional Decisions

- **`users` is not firm-scoped.** Sign-in looks the username up before any firm is known; the user's
  `firm_id` is what scopes everything they read. In Phase 1 the `operador` belongs to firm 1 too;
  multi-firm access for the operator is Phase 3 (plan AM6).
- **The header never moves a signed-in person.** Otherwise `X-Firm` would be a way into another
  firm's leads.

## Known Gaps

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
