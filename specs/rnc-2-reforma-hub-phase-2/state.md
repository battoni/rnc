# State: Franco Reforma Hub — Phase 2
Stage: plan
Updated: 07/11/2026
## Done
Intent approved 07/11 · spec closed 07/11 (29 R · 24 A · 15 D) · plan approved 07/11 (F1–F10, after a clean-context Sonnet review — plan.md → Plan review). Nothing built yet. Branch `feature/rnc-2-reforma-hub-phase-2` starts from the finished `feature/rnc-1-reforma-hub-pilot` (8ed5ef3); rnc-1 is not on `main` yet.
## Next
1. Read the W# list below and the spec's D1–D15 (every one taken while the owner was away).
2. Build: `/loop /battoni-run rnc-2` (F1 Partner access first), or attended `/battoni-start-session rnc-2` per phase.
3. Before verify's PR: rnc-1 must reach the remote first (its state.md → Next 2); this branch's PR bases on `feature/rnc-1-reforma-hub-pilot` until rnc-1 is merged.
## Pitfalls
- Every rnc-1 pitfall still holds (`specs/rnc-1-reforma-hub-pilot/state.md` → Pitfalls); the plan's P1–P10 carry the ones that bite here.
- `facts.md` §5 (NF-e tag paths, CRT codes, `verProc`) is general knowledge, not fetched: the parser reads defensively and the screens call the figures estimates.
- From F1 on, a new route not classified in `tests/Feature/Access/routeClasses.php` fails A2 — classify it in the same phase.
- The operator holds every key but has no `partner_id`: the portal answers it 403 `portal.noPartner`, by design.
## QA
- None yet.
## Local environment
- As rnc-1 (`specs/rnc-1-reforma-hub-pilot/state.md` → Local environment). F1 adds partner logins `parceiro.1`…`parceiro.8` (password `franco-local`, `SEEDER_PWD`), one per seeded "(exemplo)" partner — listed here by F1.
## Away run
Stop at: PR after verify · Reviewer: code-reviewer (Sonnet) · Chrome QA: on
## Decided while you were away
- W1 intent: the scope of Phase 2 → the six modules of `battonidev-1` `commercial.md` §1/§3 and `seed.js` MODULES phase 2 (partner portal + ledger 25 h, chat 20, NF-e readiness 25, supplier credit 25, split payment 12, Radar with XML 30 = 137 h), nothing from Phase 3 (to reverse: reopen the intent).
- W2 intent: the proposal page → skipped; the approved demo is the visual and behaviour reference, as in rnc-1 (to reverse: `/battoni-new-proposal rnc-2 final`).
- W3 spec: the demo's network agenda and joint meetings (`network.js`, the partner's "Serviços e agenda" tab) → out of scope: the module's menu line names inbox, progress and the statement only (to reverse: a new R# for a portal agenda tab).
- W4 spec: the demo shows the lead's contact to the partner at once → only after the partner accepts (D3; to reverse: drop the condition in `PortalReferralResource`).
- W5 spec: the demo's NF-e screen says "notas de compra" in its detail but "quais clientes já emitem" in its module line → readiness measures the client's own issued invoices (saída), where the client's system must change; purchases feed supplier credit (to reverse: switch `ReadinessService` to `entrada`).
- W6 spec: the demo's three supplier regimes → two (Simples/MEI · regime normal), because the CRT cannot tell Presumido from Real (D8; to reverse: a supplier CNPJ lookup).
- W7 spec: the demo's single figures for supplier credit (4 % DAS) and split payment (50 % settled) → ranges 3–5 % and 40–60 %, because the constraint asks every figure as a range (to reverse: the calculators' constants).
- W8 spec: the demo's ERP column → the emitting system read from `ide/verProc` (clients carry no ERP field; `facts.md` §5) (to reverse: an `erp` column on `clients`).
- W9 spec: an LLM for the chat → none; a scripted receptionist with a typed-text matcher (D11). No real driver for SEFAZ, a model or a bank is written at all, so nothing can call out (A24) (to reverse: bind a driver to `NfeDocumentSource`, add a server receptionist contract).
- W10 spec: who sees the fiscal screens → equipe + ceo read, ceo imports and runs the Radar (D14; to reverse: `RoleSeeder`).
- W11 plan: the high-risk Fable plan review (permissions, who sees what) → skipped: this session carries no `#fable`; the Sonnet clean-context review ran instead and the verify review stays (to reverse: run `fable-reviewer` on spec + plan before F1).
- W12 plan: the away settings, normally asked of the owner → stop at the PR after verify, `code-reviewer` on Sonnet (no `#fable`), Chrome QA on — rnc-1's choices except the reviewer (to reverse: edit the block above before `/battoni-run`).
- W13 plan: the review found F2 at ~25 files → the ledger became its own phase (F4), so the plan has 10 phases, one over the 6–9 aimed at; the other phases keep rnc-1's size (to reverse: fold F4 back into F2 and F3).
- W14 plan: open points a builder would have stopped on (operator keys, the 409 code, a document between two clients, subscription months, band revenue, the batch's order/score/message) → answered in the phases themselves (plan.md → Plan review; to reverse: edit the phase).
