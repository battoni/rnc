---
version: 1.1.0
origin: rnc
---
# RULES — Diagnostic Module

> For AI agents. rnc-1 F3, 07/11/2026.

## Domain

The public Reforma diagnostic (R9–R14): the CNPJ lookup, the rules engine and the five public
routes a visitor walks, all by lead token. Owns no table: every write to the lead goes through
`LeadService` (the Lead module owns `leads`, `lead_consents`, `lead_events`).

## The routes (`routes/api.local.php`, plan AM3)

| Route | What |
| --- | --- |
| `POST public/diagnostics` | step 1 starts: a `visitante` lead and its token (20/min) |
| `POST public/diagnostics/{token}/cnpj` | the CNPJ path's lookup (10/min per address, AM10) |
| `PATCH public/diagnostics/{token}` | the contact (→ `lead_parcial`) and the later steps (60/min) |
| `GET public/diagnostics/{token}/result` | count, titles, ranges (→ `lead`) |
| `GET public/diagnostics/{token}/simulation` | 2026–2033 mix + split payment |

- **An unknown, expired or other firm's token is the same 404** on every route.
- **Each throttle has its own prefix** (`throttle:10,1,cnpj-lookup`): Laravel's plain
  `throttle:N,M` keys by address alone, so without a prefix every such route shares one counter.

## Intentional Decisions

- **`CnpjLookup` is a contract** bound to `BrasilApiCnpjLookup` by `#[Bind]` on the interface (no
  provider edit). 5 s timeout, a found company cached 30 days under `cnpj:{14 digits}`, a failure
  never cached. Porte ME → b1, EPP → b2 (by `codigo_porte` 1/3 or the name), anything else asked;
  `opcao_pelo_simples` or `opcao_pelo_mei` true → `simples`, otherwise asked (W21); the sector by
  the main CNAE's first two digits (`Sector::fromCnae`). The check digits are validated in
  `LookupCnpjRequest`, before any call.
- **An unknown CNPJ and a failed call look the same** (`lookup: unavailable`): the screen offers
  the quiz, never an error (D3). The CNPJ typed is kept on the lead either way.
- **The rules engine is pure** (`RulesEngine`): `diagnose()`, `taxMix()`, `splitPaymentRange()`,
  `formatRange()` ported from the demo's `engine.js`. The rule table is the `DiagnosticRule` enum —
  one case per line of facts.md §2, in result order, each with its kind, title, cited rule, detail
  and share of the band's revenue. `RulesEngineTest` has a case per rule.
- **A public answer never carries a detail or a cited rule** (R13): `PublicDiagnosticResultResource`
  is an allow-list of kind, title and range. The detail is the team's (F5).
- **`nao_sei` is a regime answer** that matches no regime rule; the simulator then uses the
  Presumido PIS/Cofins rate, as the demo did for anything but Real.

## Tables read from other modules

`leads` (Lead), through `LeadService::findLeadByToken`.

## Known Gaps

- **The BrasilAPI mapping is from its documented shape, not a live call**: tests use `Http::fake`
  and the network is never reached (`Http::preventStrayRequests`). Verify one real CNPJ before launch.
- **No CAPTCHA** on the public routes: the throttles stop a loop from one address, not a crowd.
