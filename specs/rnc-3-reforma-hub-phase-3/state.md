# State: Franco Reforma Hub — Phase 3
Stage: plan
Updated: 07/11/2026
## Done
Intent approved 07/11 · spec closed 07/11 (29 R · 27 A · 15 D) · plan approved 07/11 (F1–F9 with F3 split into F3a/F3b, after a clean-context Sonnet review — plan.md → Amendments AM1–AM14, Plan review). Nothing built yet. Branch `feature/rnc-3-reforma-hub-phase-3` starts from `feature/rnc-2-reforma-hub-phase-2` at its F1 (454b0c5); neither rnc-1 nor rnc-2 is on `main`.
## Next
1. Read the W# list below and the spec's D1–D15 (every one taken while the owner was away).
2. Build: `/loop /battoni-run rnc-3` — F1 Brands API first (read plan.md → "Carried up front", P0 merges rnc-2 before each phase), or attended `/battoni-start-session rnc-3` per phase.
3. F6–F9 wait for rnc-2 to be finished (P0): if it is not when F6 is due, the run stops "blocked on rnc-2".
4. Before verify's PR: rnc-1 and rnc-2 must reach the remote first; this branch's PR bases on `feature/rnc-2-reforma-hub-phase-2` until rnc-2 is merged.
## Pitfalls
- Every rnc-1 and rnc-2 pitfall still holds (`specs/rnc-1-reforma-hub-pilot/state.md`, `specs/rnc-2-reforma-hub-phase-2/state.md` → Pitfalls).
- This worktree runs the gate as `E2E_PORT=5176 make pre-commit`; with the default port Playwright reuses another checkout's server on 5173 (`app.rnc/e2e/RULES.md`). Live checks: API on 8013, SPA on 5176, its own SQLite.
- rnc-2 is built in parallel in `rnc-wt-2` and folds columns into the same `create_*` migrations (`leads`, `referrals`, `firms`, `clients`): expect merge conflicts there; keep both column sets, then `make fresh-seed`.
- `users` is not firm-scoped and the operator has `firm_id` set: there is no cross-firm operator access (Firm `RULES.md`), so every Phase 3 screen works inside Franco's firm.
- The SPA never sent `X-Firm`; F1 adds `X-Brand` only on public calls — never on back-office calls, where the brand is irrelevant.
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
- W20 plan: the high-risk Fable plan review (permissions, who sees what) → skipped: no `#fable` in this session; the clean-context Sonnet review ran, and the verify review covers the diff (to reverse: run `fable-reviewer` on spec + plan before F1).
- W21 plan: the away settings, normally asked of the owner → stop at the PR after verify, `code-reviewer` on Sonnet, Chrome QA on — rnc-2's choices (to reverse: edit the block above before `/battoni-run`).
- W22 plan: the review's 29 findings → 14 amendments (AM1–AM14: the F3 split, the knowledge base under Equipe so specialists can review, exact tie/day-90/quartile/years rules, the global stray-request guard, schedule and clause tests) and the spec fixes they name; the "too many files" finding was rejected for every phase but F3, as rnc-2 did (to reverse: split F1, F4 or F6 the same way).
