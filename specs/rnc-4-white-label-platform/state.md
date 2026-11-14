# State: Reforma hub as a white-label platform for accounting firms
Stage: build F1a/19
Updated: 13/11/2026
Branch: feature/rnc-4-white-label-platform · Worktree: .claude/worktrees/rnc-4
## Done
Intent approved 13/11 (delegated) · spec closed 13/11 (40 R · 49 A · 31 D, owner decisions O1–O12) ·
plan approved 13/11 (delegated): F1a F1b F2a F2b F2c F3a F3b F3c F4a F4b F6a F6b F5a F5b F4c F4d F7
(+ optional F9a F9b), then verify. Nothing built. Baseline suites: 1851 Pest · 1289 Vitest · 180 Playwright.
## Next
1. Plan review done (plan.md → `## Plan review`, AM1–AM10 override the phases; F2c and F3b split).
2. `/loop /battoni-run rnc-4` → F1a Firm by host — API (plan.md). Phases run in the listed order.
## Pitfalls
- (plan) `phpunit.xml` pins `DEFAULT_FIRM_SLUG=franco` (D6): a test about the platform firm must
  create `hub` itself and send no `X-Firm`.
- (plan) From F1b, `localhost:5173` is the PLATFORM firm, not Franco: Franco is
  `franco.localhost:5173`. Never use `127.0.0.1` — cookies and Sanctum's stateful list are per host.
- (plan) The SPA reaches the API only through Vite's `/api` proxy (D4); a direct `localhost:8000`
  call from a firm host carries no session.
- (plan) `battoni` belongs to Franco until F4a moves it to no firm; from F4a it signs in only at the root.
- (plan) The role is `ceo` until F2c renames it `gestao`; seeds written before F2c use `ceo`.
## QA
(none yet — rounds in plan.md → QA)
## Seeded people (password `seeding.password`; `battoni` uses `seeding.superadmin_password`)
- Platform: `battoni` — operador (firm `franco` until F4a, then no firm, root only).
- Platform firm `hub` (F1a; manager from F3b): `gestao.hub`.
- Franco (`franco`, unchanged from rnc-3): `reinaldo` (ceo → `gestao` in F2c), `patricia` (equipe),
  specialists `mariana.toledo`, `carlos.prado`, `juliana.reis`, `rafael.antunes`; partners
  `parceiro.1`…`parceiro.8`.
- Demo firm Alvorada (`alvorada`, invented; F1a minimal, F3b full): `helena.alvorada` (manager),
  `equipe.alvorada` (team), specialists `bruno.alvorada`, `camila.alvorada`; partners
  `parceiro.alvorada.1`… (F3b).
## Local environment
- API `make serve-api` → http://localhost:8000; SPA `make serve-app` → http://localhost:5173 (root =
  platform firm), http://franco.localhost:5173, http://alvorada.localhost:5173; `make fresh-seed`
  rebuilds `api.rnc/database/database.sqlite`. Main checkout's stack stopped while this worktree serves.
- API `.env` needs `DEFAULT_FIRM_SLUG=hub` and `SANCTUM_STATEFUL_DOMAINS=localhost:5173,*.localhost:5173`
  (F1a writes `.env.example`; copy the two lines into the worktree's `.env`, which is gitignored).
- Mail goes to `api.rnc/storage/logs/laravel.log` (`MAIL_MAILER=log`).
## Away run
Stop at: draft PR after verify · Reviewer: fable-reviewer (#fable session) · Chrome QA: on
Started: 2026-11-13 23:58
## Decided while you were away
- W1 plan: proposal.html skipped in away mode (spec D27; to reverse: `/battoni-new-proposal rnc-4 final`).
- W2 plan: the owner's phases are split into 17 builder phases with his labels kept (spec D28); his
  "F4b logo + view-as" is F4c/F4d because the console itself needed F4a (API) + F4b (SPA) (to reverse: n/a).
- W3 plan: human QA round off; Chrome rounds by role only (plan.md → QA; to reverse: `/battoni-new-qa-session rnc-4`).
