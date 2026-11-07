# State: Franco Reforma Hub — Phase 2
Stage: build
Updated: 07/11/2026
## Done
Intent approved 07/11 · spec closed 07/11 (29 R · 24 A · 15 D) · plan approved 07/11 (F1–F10 with F6 split into F6a/F6b, after a clean-context Sonnet review and a `fable-reviewer` review — plan.md → Plan review, Amendments AM1–AM13). Nothing built yet. Branch `feature/rnc-2-reforma-hub-phase-2` starts from the finished `feature/rnc-1-reforma-hub-pilot` (8ed5ef3); rnc-1 is not on `main` yet.
- **F1 Partner access — done 07/11** (commit in the log line below; A1, A2, A4): `parceiro` role, `users.partner_id` (unique), `partner_access_events`, `POST/DELETE partners/{id}/access` + `…/access/password`, `CurrentPartner` + `ResolveCurrentPartner` (`portal`), `GET portal/me`, the route matrix (`tests/Feature/Access`), the Portal SPA module (`/parceiro`), Home's bounce, the rail entry, "Acesso ao portal" in the registry, `PartnerUserSeeder`. Gate: 822 Pest · 884 Vitest · 122 Playwright. `parceiro.1` signed in against the served worktree API (port 8012, stopped after): `GET portal/me` 200, `GET leads` 403.
## Next
1. Read the W# list below and the spec's D1–D15 (every one taken while the owner was away).
2. Build: `/loop /battoni-run rnc-2` (F1 Partner access first; read plan.md → Amendments before each phase), or attended `/battoni-start-session rnc-2` per phase.
3. Before verify's PR: rnc-1 must reach the remote first (its state.md → Next 2); this branch's PR bases on `feature/rnc-1-reforma-hub-pilot` until rnc-1 is merged.
## Pitfalls
- Every rnc-1 pitfall still holds (`specs/rnc-1-reforma-hub-pilot/state.md` → Pitfalls); the plan's P1–P10 carry the ones that bite here.
- `facts.md` §5 (NF-e tag paths, CRT codes, `verProc`) is general knowledge, not fetched: the parser reads defensively and the screens call the figures estimates.
- From F1 on, a new route not classified in `tests/Feature/Access/routeClasses.php` fails A2 — classify it in the same phase.
- The operator holds every key but has no `partner_id`: the portal answers it 403 `portal.noPartner`, by design.
- Pest helper functions are global across files: prefix them per file (`access*`, `portal*`, `matrix*`), or two files declaring one name is fatal.
- A Pest test that replays a real cookie must forget the guards AND `app()->forgetInstance('auth.driver')`: otherwise the `sessions` row is written with the previous `actingAs` user's id.
- `CarbonImmutable::setTestNow('2026-11-09 13:00:00')` is São Paulo time (APP_TIMEZONE); a UTC assertion needs `parse('…Z')`.
- SPA specs: `translatedMessage` is set by the axios interceptor, which `renderWithPlugins` does not install — branch refusals on the API's `code` (`PARTNER_ACCESS_ERROR_KEYS`), never on the sentence.
- `npm run sort-locales` sorts only the core `en.json`/`pt-BR.json`; the LOCAL files under `locales/local/` keep append order (as rnc-1 left them).
- Every new portal route goes in `routes/api.local.php` with `$portal` (`auth`, `permission:portal.access`, `portal`) and in `routeClasses.php` as `portal`; the matrix also checks the middleware structurally.
## QA
- None yet.
## Local environment
- As rnc-1 (`specs/rnc-1-reforma-hub-pilot/state.md` → Local environment). F1 adds partner logins `parceiro.1`…`parceiro.8` (password `franco-local`, `SEEDER_PWD`), one per seeded "(exemplo)" partner in id order: `parceiro.1` Andrade Tributário, `.2` Recupera Créditos, `.3` NexoERP, `.4` Giro Capital, `.5` Amparo Seguros, `.6` Herança Societário, `.7` Margem Certa, `.8` CertiDigital (e-mail `portalN@parceiro.exemplo`). Each lands on `/parceiro`.
- A second checkout (the worktree `rnc-wt-2`) runs the gate as `E2E_PORT=5175 make pre-commit` (Node 24): with the default port Playwright reuses the other checkout's server on 5173 (`app.rnc/e2e/RULES.md`). Its SQLite is its own `api.rnc/database/database.sqlite`.
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
- ~~W11 plan: the high-risk Fable plan review → skipped~~ — retired 07/11: the owner granted `#fable`, so `fable-reviewer` reviewed spec + plan; its 13 findings are plan.md → Amendments AM1–AM13 (and spec C1). The verify review stays.
- W12 plan: the away settings, normally asked of the owner → stop at the PR after verify, `code-reviewer` on Sonnet (no `#fable`), Chrome QA on — rnc-1's choices except the reviewer (to reverse: edit the block above before `/battoni-run`).
- W13 plan: the review found F2 at ~25 files → the ledger became its own phase (F4), so the plan has 10 phases, one over the 6–9 aimed at; the other phases keep rnc-1's size (to reverse: fold F4 back into F2 and F3).
- W14 plan: open points a builder would have stopped on (operator keys, the 409 code, a document between two clients, subscription months, band revenue, the batch's order/score/message) → answered in the phases themselves (plan.md → Plan review; to reverse: edit the phase).
- W15 F1: the partner login's `users`/`sessions` writes → Auth's new `LinkedAccountService` (Auth owns those tables; arcus "a module never writes another's tables"); `PartnerAccessService` decides when (to reverse: move the writes into `PartnerAccessService`).
- W16 F1: a regrant reopens the partner's one `users` row instead of creating a new one, so `users.partner_id` is unique and that index answers two racing grants with 409 (to reverse: drop the unique, create a user per grant, and filter "active" in the lookup).
- W17 F1: no `ThePortalLayout` — the portal renders in `TheLayout` and the rail makes it the partner's (portal entry gated on `portal.access` AND role `parceiro`). The core Home entry stays in a partner's rail and leads to the portal through Home's redirect: gating it on `home.read` rewrites TheNavbar's core contract (its specs assume Home for every account) (to reverse: a third layout, or `permission: 'home.read'` on `CORE_NAV_ITEMS`).
- W18 F1: an inactive partner gets no login (409 `partner_inactive`) and `ResolveCurrentPartner` requires an active partner — beyond the plan, defence in depth for R2 (to reverse: drop the two checks).
- W19 F1: `PartnerResource` gains `access` (the login, never the password) so the registry knows whether to offer create or regenerate/revoke, eager-loaded in the list (to reverse: a `GET partners/{id}/access`).
- W20 F1: Home's bounce lives in `HomeView` (`router.replace`, renders nothing), not in a route guard: a route file importing the user store recreates the barrel cycle `attachAuthGuard` documents (to reverse: a `beforeEnter` once that cycle is gone).
- W21 F1: pendulum-origin units whose only change is a LOCAL file (`TheNavbar/navItems.local.*`, `locales/local/*`) or a new e2e spec get no CHANGELOG bump, as rnc-1 did — a bump would mark them diverged for the sync triage. Units with real code changes were bumped (Auth Services/Repositories/Resources/DTOs, app/Models, database, Home, HomeView) (to reverse: add the entries).
