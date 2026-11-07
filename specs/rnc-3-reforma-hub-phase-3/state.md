# State: Franco Reforma Hub — Phase 3
Stage: build (F1 done)
Updated: 07/11/2026
## Done
Intent approved 07/11 · spec closed 07/11 (29 R · 27 A · 15 D) · plan approved 07/11 (F1–F9 with F3 split into F3a/F3b, after a clean-context Sonnet review — plan.md → Amendments AM1–AM14, Plan review — and a Fable review, AM15–AM23). Branch `feature/rnc-3-reforma-hub-phase-3` starts from `feature/rnc-2-reforma-hub-phase-2` at its F1 (454b0c5); neither rnc-1 nor rnc-2 is on `main`.
- **F1 Brands API** — built 07/11 (commit `56cfa70`), pre-commit green 07/11 (`E2E_PORT=5176`): `firm_brands` + `FirmBrand`, `CurrentBrand`, `ResolveCurrentBrand` (alias `brand` on every `public/*` route, `brand:fallback` on events — AM21), `Contrast`, the brands console API (`GET brands`, `PATCH brands/{id}`, `POST brands/{id}/activate|deactivate`, `brands.manage`), `PublicFirmResource::underBrand`, `leads.brand_id` fixed at the start, the brand's consent words and mail sender, `GET leads?brand=`, the chip on card and detail, `BrandSeeder` + ~10 % Express/Prime leads, R28's seven keys declared (operator only), `Http::preventStrayRequests()` in `tests/Pest.php` (AM18; no inherited test reached real HTTP). Proofs: A1 `PublicBrandTest`, A3 `BrandLeadTest`, A4 (Pest) `BrandFilterTest`, A5 `BrandAdminTest`, A25 brand rows `Phase3AccessTest`, `ContrastTest`, `StrayRequestGuardTest`. Red steps: every new test failed before its code; then 15 mutations (firm-blind brand lookup, inactive served, no events fallback, contact overlaid, no contrast rule, per-brand count query, brand not stored / moved on restart, consent without brand, mail from `mail.from`, mail signed by the firm, no main filter, filter on any firm's slug, lazy brand per card, ceo granted `brands.manage`) each turned its test red, restored; the stray guard went red without the `Pest.php` line; the seeder test red without `BrandSeeder` in the chain. Live: `curl -H 'X-Brand: express' localhost:8013/api/public/firm` → Franco Express Contabilidade, 11 3732-5500, #3b2a86; `X-Brand: nada` → 404 `brands.notFound`. Schema diffed before/after: only `firm_brands` and `leads.brand_id` (+ index). Suites: 918 Pest · 906 Vitest · 122 Playwright, all green (baseline 822 · 906 · 122).
## Next
1. Read the W# list below and the spec's D1–D15 (every one taken while the owner was away).
2. Build: `/loop /battoni-run rnc-3` — next F2 Brands on the site and the console (read plan.md → "Carried up front", P0 merges rnc-2 before each phase), or attended `/battoni-start-session rnc-3 F2`. F2 reads the API F1 left: `GET public/firm` → `brand`, `short_name`, `tagline`, `proof` (null on the main brand), 404 `brands.notFound` on any public route; `GET brands` → `{main, brands[]}`; the card's `brand {id, short_name}`.
3. F6–F9 wait for rnc-2 to be finished (P0): if it is not when F6 is due, the run stops "blocked on rnc-2".
4. Before verify's PR: rnc-1 and rnc-2 must reach the remote first; this branch's PR bases on `feature/rnc-2-reforma-hub-phase-2` until rnc-2 is merged.
## Pitfalls
- Every rnc-1 and rnc-2 pitfall still holds (`specs/rnc-1-reforma-hub-pilot/state.md`, `specs/rnc-2-reforma-hub-phase-2/state.md` → Pitfalls).
- This worktree runs the gate as `E2E_PORT=5176 make pre-commit`; with the default port Playwright reuses another checkout's server on 5173 (`app.rnc/e2e/RULES.md`). Live checks: API on 8013, SPA on 5176, its own SQLite.
- rnc-2 is built in parallel in `rnc-wt-2` and folds columns into the same `create_*` migrations (`leads`, `referrals`, `firms`, `clients`): expect merge conflicts there; keep both column sets, then `make fresh-seed`.
- `users` is not firm-scoped and the operator has `firm_id` set: there is no cross-firm operator access (Firm `RULES.md`), so every Phase 3 screen works inside Franco's firm.
- The SPA never sent `X-Firm`; F1 adds `X-Brand` only on public calls — never on back-office calls, where the brand is irrelevant.
- (F1) The Bash hook (rtk) rewrites commands and auto mode then refuses them: call tools by absolute path (`/usr/bin/grep`, `/bin/cat`) or through a script, and run the gate from a script.
- (F1) `check-api` runs `pint --test` BEFORE `sanitize` fixes anything: run `vendor/bin/pint` on touched PHP files before the gate, or it stops at the static checks.
- (F1) PHPStan refuses `$x?->y ?? z` (`nullsafe.neverNull`): `??` already tolerates null, write `$x->y ?? z`.
- (F1) In a test, `refresh()` after a request reads under the firm that request left in `CurrentFirm`; another firm's row needs `withoutGlobalScopes()`.
- (F1) A new permission group needs its `rolesAndPermissions.groups.<id>` (and any new `permissions.<label>`) in `app.rnc/src/locales/local/{en,pt-BR}.json`.
- (F1) Phase 3 migrations use the `2026_11_07_3000NN` sequence so their file names never collide with rnc-2's.
## QA
- None yet.
## Local environment
- As rnc-1 and rnc-2 (their state.md → Local environment). Phase 3 adds: `?marca=express` / `?marca=prime` on the site, the 6 "(exemplo)" RNC firms, the 10 assistant notes (one draft), 40 contracts.
## Away run
Stop at: PR after verify · Reviewer: code-reviewer (Sonnet) · Chrome QA: on
## Decided while you were away
- W1 intent: the scope → the five modules of `commercial.md` §3 and `seed.js` MODULES phase 3 (contracts 20 h, sub-brands 30, RNC 60, briefing 15, assistant 60 = 185 h), including the assistant that §1 had put "Later" — §3 is the later decision (to reverse: drop R10–R16 and F4–F5).
- W2 intent: "RNC channel" → Franco as the sales channel to other Rede Nacional de Contabilidade firms (`commercial.md` §1), not a video channel; so no video or channel host exists in scope (to reverse: a new intent).
- W3 intent: the proposal page → skipped; the approved demo is the visual and behaviour reference, as in rnc-1/rnc-2 (to reverse: `/battoni-new-proposal rnc-3 final`).
- W4 spec: Express/Prime → brands of the one Franco firm, not separate firms (D1; to reverse: `parent_firm_id`).
- W5 spec: brand on the public site → `?marca=` + `X-Brand`, because there is one local host; host mapping is a Known Gap (D2; to reverse: a `host` column read by `ResolveCurrentBrand`).
- W6 spec: brands are edited by the operator only (D3; to reverse: grant `brands.manage` to ceo).
- W7 spec: a contrast rule on brand colours (white on primary ≥ 4,5:1), beyond the demo (D4; to reverse: drop it in `UpdateBrandRequest`).
- W8 spec: RNC firms are pipeline records with no login and no tenant (D5); Reinaldo sees the channel read-only, beyond the demo's operator-only screen, because the share is Franco's money (R9; to reverse: drop `channel.read` from ceo).
- W9 spec: the assistant has no model driver at all — only the deterministic `curated` driver over reviewed notes (D9); a model is then off by default by construction and needs no key (to reverse: add a driver to `ASSISTANT_DRIVERS`).
- W10 spec: note review is four-eyes (a specialist who is not the last editor) and lasts 90 days, beyond the demo (D8; to reverse: `AssistantNote::VALID_DAYS` and the policy).
- W11 spec: the demo's hand-off always went to the first specialist → a picker of active specialists, defaulting to the first by name, plus an answer inbox and "Virar nota" (R13, R14; to reverse: drop the picker).
- W12 spec: `credito-simples` is seeded as a draft, not reviewed, because `facts.md` §2 marks that rule unverified (to reverse: review it in "Base da Reforma" once a specialist confirms).
- W13 spec: contract values are yearly and exposure is capped at 2033 (the demo's seed never passes it; D10; to reverse: `ContractExposure::LAST_YEAR`, a total-value column).
- W14 spec: the legal referral uses a `subject_key` in the referral's unique key so a contract and a Radar referral of one client to one partner coexist (D11; to reverse: drop `subject_key`).
- W15 spec: legal partners are picked by specialty (advocacia tributária, then precificação), as the demo, not by rule coverage as `battonidev-1` R28 says (to reverse: `PartnerMatcher` on a contract rule).
- W16 spec: the briefing's pipeline in R$ uses the portfolio's own fee quartiles by band, as a range (the demo's lead fee is invented; D12; to reverse: a fixed table).
- W17 spec: the briefing's "meetings" tile counts the next 5 business days with today as the sub-line, and "new leads" 7 days with 24 h as the sub-line — the demo's numbers, where `battonidev-1` R22 said only "meetings today" (to reverse: the windows in `BriefingService`).
- W18 spec: the daily 07:00 e-mail is included, business days only (D13; to reverse: unschedule `briefing:send`).
- W19 plan: F6–F9 depend on rnc-2 being finished; F1–F5 depend only on rnc-1 + rnc-2's F1, so they go first, and every phase merges rnc-2's branch when it moved (P0; to reverse: build after rnc-2 lands, in spec order).
- W20 (retired, 07/11): "Fable plan review skipped" was wrong — the owner had granted `#fable`; `fable-reviewer` reviewed the plan (no AM1–AM14 judged wrong; W2, W4, W9/W10 confirmed) and its findings are plan.md → AM15–AM23.
- W21 plan: the away settings, normally asked of the owner → stop at the PR after verify, `code-reviewer` on Sonnet, Chrome QA on — rnc-2's choices (to reverse: edit the block above before `/battoni-run`).
- W23 plan: the Fable review's 9 findings → AM15–AM23 (Reinaldo sees no platform price, a re-import never downgrades a marked clause, exposure formula, stray-request guard from F1, note expiry counter and bell, partner sees a value range, stale brand fallback, red steps, D1 citation); to reverse each, see its AM.
- W24 F1: the consent under a sub-brand → the demo's sentence with the brand's full name, "Autorizo a Franco Prime Contabilidade a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar." — the network stays Franco's (D1), the main brand's words and `VERSION` 1 unchanged (to reverse: `ConsentTerms::BRAND_TEXT`).
- W25 F1: the `brand` middleware is on every `public/*` route (sectors, diagnostics, booking, report), not only `public/firm`, so any public call can answer the 404 `brands.notFound` the SPA clears on (spec C1; to reverse: drop `$brand` from a route line).
- W26 F1: the main brand answers `brand`, `short_name`, `tagline` and `proof` as null — `firms` has no such columns and the site keeps its own Franco copy (to reverse: columns on `firms`, filled by `FirmSeeder`).
- W27 F1: the sender is the brand's (or firm's) name at the firm's mailbox for the reminder too and for main-brand leads (before: `mail.from`, "rnc" at `nao-responda@…`), as A3 reads; the unverified mailbox domain is a Booking Known Gap (to reverse: `MeetingMailDTO::sender()` → null).
- W28 F1: codes the plan left open → a bad colour is 422 `brands.invalidColour`, an unknown board-filter slug 422 `brands.notFound` on `brand`; a deactivated brand stays filterable; colours are stored lower-case; `main` in the filter is `Lead::MAIN_BRAND = 0` (to reverse: `UpdateBrandRequest`/`ListLeadsRequest` messages).
- W29 F1: the stray-request guard is on `Feature` tests only — Unit tests boot no application — and its proof in F1 is `StrayRequestGuardTest` (an unfaked `Http::get` throws); A27's line assertion and its red step stay F9's (to reverse: `tests/Pest.php`).
- W30 F1: the "Marca principal" row of `GET brands` is a `MainBrandResource` (name, colours, lead count) and brands are listed by name; no brand is created through the API — Express and Prime are `BrandSeeder` rows (Firm Known Gap; to reverse: a `POST brands`).
- W22 plan: the review's 29 findings → 14 amendments (AM1–AM14: the F3 split, the knowledge base under Equipe so specialists can review, exact tie/day-90/quartile/years rules, the global stray-request guard, schedule and clause tests) and the spec fixes they name; the "too many files" finding was rejected for every phase but F3, as rnc-2 did (to reverse: split F1, F4 or F6 the same way).
