---
version: 1.1.0
origin: rnc
---
# RULES — Firm Module

> For AI agents. rnc-1 F1, 07/11/2026.

## Domain

The tenants (D1): an accounting firm whose hub this is. Franco Contabilidade is firm 1
(`FirmSeeder`); the RNC firms and the Express/Prime sub-brands of Phase 3 are more rows.

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

## Intentional Decisions

- **`users` is not firm-scoped.** Sign-in looks the username up before any firm is known; the user's
  `firm_id` is what scopes everything they read. In Phase 1 the `operador` belongs to firm 1 too;
  multi-firm access for the operator is Phase 3 (plan AM6).
- **The header never moves a signed-in person.** Otherwise `X-Firm` would be a way into another
  firm's leads.

## Known Gaps

- **The first models on `BelongsToFirm` are F2's** `SectorPage`, `Visitor` and `TrackingEvent`; the
  scope itself is proven on a test-only table (`tests/Feature/Firm/FirmScopeTest.php`, A1). (This
  line used to say F3's `Lead` would be the first and kept a `trait.unused` PHPStan entry until then;
  F2 deleted the entry.)
- **No firm admin screen.** Firms are seeded; a second firm is a seeder row until Phase 3.
