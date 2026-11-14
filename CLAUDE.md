# rnc — Franco Reforma Hub

The product repo of **Franco Contabilidade's Reforma Tributária hub** (Battoni Dev, feature
`rnc-1`, the Phase 1 pilot): a public site with the impact diagnostic for business owners, and a back
office where Franco's team works the leads, Reinaldo (CEO) runs the partner network, the metrics
and the fee repricing, and Battoni Dev operates the product. Multi-firm from day one — every
business table carries `firm_id` (the `Firm` module, `BelongsToFirm`) — so the RNC firms and the
Express/Prime sub-brands of Phase 3 are rows, not deploys. UI strings are pt-BR; the brand is
navy `#00375a` with cyan `#14c3ff` and the text logo "Franco Contabilidade". The partner network's
founder and its acronym never appear in the product.

Bootstrapped from pendulum (`/battoni-setup-project`); the sync tooling still reads `.cortex-sync.conf`.

## Sub-projects

| Project | Type | Description |
| --- | --- | --- |
| `api.rnc/` | Laravel API | The hub's API: firms (tenancy), auth and the three roles (`operador`, `ceo`, `equipe` + `is_specialist`), and from F2 on content, tracking, diagnostic, booking, pipeline, partners, metrics and repricing. Pest on in-memory SQLite, the app on MySQL 8 (database `rnc`) |
| `app.rnc/` | Vue 3 SPA | One SPA, two shells: the public site (`ThePublicLayout`, `Site` module, routes open to everyone) and the back office (`TheLayout`, a rail filtered by permission: Equipe · Reinaldo · Operador). Theme `franco` |
| `codelumen/` | VitePress | Code conventions handbook (the long form of the rules) |
| `scripts/` | Python/Shell | Root tooling: `pint-changed.sh` / `phpstan-changed.sh` run Pint and PHPStan on the api.rnc files changed on the branch; `worktree.py` (`rtk make wt-new`/`wt-down`/`wt-discard`/`wt-gc`, tests `rtk make wt-test`) gives each piece of work its own worktree under `.claude/worktrees/<id>`, tears it down once its PR merged, throws an unmerged one away on a confirmed `wt-discard` (a dry run until `CONFIRM=<id>`), and prints this checkout's e2e port for `rtk make e2e` (`worktree.py port`); rnc is SQLite and has no Valet, so `wt-up`/`wt-app` do not apply (`.claude/feature-flow.md`, "Worktrees"); `flow-status.py` (`/battoni-status`) lists the ongoing work of the current project, or of every flow project under `~/Work` with `--all`, tests in `test_flow_status.py` |
| `cortex/` | AI knowledge | The feature flow's phase loop and playbooks, the sync engine, the hook tests |

Feature IDs are `rnc-<n>` (`TASK_PREFIX` in `.cortex-sync.conf`); feature folders live in `specs/`.
Local stack: `make serve-api` (http://localhost:8000), `make serve-app` (http://localhost:5173),
`make fresh-seed` (`Makefile.local`); the seeded people are in the current feature's `state.md`.

## AI tooling

| Layer | What it does |
| --- | --- |
| `CLAUDE.md` | Always-loaded project context (`.cursor/rules/project-context.mdc` carries the same body for Cursor) |
| `.claude/rules/` | `arcus-api-architecture`, `celer-01` … `celer-09`, `shared-conventions` |
| `.claude/skills/` | Demand-loaded specializations (12): `arcus-external-integrations` (payment/messaging providers, webhooks, idempotency), `arcus-laravel-best-practices`, `arcus-pest-testing`, `arcus-tailwindcss`, `celer-advanced-patterns`, `celer-testing` (Vitest + Playwright), `cortex-backport` (bring a downstream feature back), `cortex-sync` (RULES.md/CHANGELOG triad and the sync engine), `docs-portal` (dual-audience VitePress docs portal), `landing-page` (build/review Astro landing pages — not used here: the public site is in `app.rnc`, D4), `midnight-oil` (unattended phase-by-phase build), `phase-review` (review a plan or a built phase) |
| `.claude/commands/` | The `/battoni-*` commands (24) — start with `/battoni-start-session`, end with `/battoni-close-session`; where every feature stands is `/battoni-status` (this project; `all` for every flow project); a person's QA round is read with `/battoni-qa-result <link>`; a feature runs `/battoni-intent` → `/battoni-spec` → `/battoni-plan` → `/battoni-start-session <id>` per phase → `/battoni-verify`, or `/battoni-run <id>` to build and verify every phase with nobody there (reference: `.claude/feature-flow.md`, folders in `specs/`); the reviews are `/battoni-review-vue-conventions`, `/battoni-review-design-conventions` and `/battoni-review-arcus-code`, and `/battoni-pre-commit` runs them before a commit |
| `.claude/agents/` | `code-reviewer` (read-only PR review), `security-auditor` (security scan), `battoni-reader` (Haiku, read-only fetch) — who gets which work: `.claude/delegation-policy.md` |
| `.claude/hooks/` | `pre-edit-rules.sh` injects the `RULES.md` chain before an Edit/Write in `app.rnc/src/`; `pre-bash-guard.sh` blocks force pushes, `rm -rf /` or `~`, `DROP TABLE` and writes to a raw disk, and by the hook's `cwd` keeps the main checkout on `main` (no branch move, commit, merge, cherry-pick, revert or `gh pr checkout` there) and each worktree on its own branch; `post-edit.sh` runs Pint on the edited api.rnc PHP file and ESLint on the edited app.rnc Vue/TS file. Tests: `cortex/hooks/test_claude_hooks.py` |
| `.cursor/rules/` | Cursor mirrors in `.mdc` format — every rule and command, and the skills `celer-testing`, `cortex-backport`, `cortex-sync` and `docs-portal` (the other skills have no Cursor counterpart) — plus `celer-folder-*` and the always-loaded `project-context.mdc`. Bodies stay identical to `.claude`: `make sync-mirror-check` |

## Toolchain

- **Node 24** for app.rnc (`source ~/.nvm/nvm.sh && nvm use 24`; the shell default is 20). On Node 20 the Vitest suite dies with `ERR_REQUIRE_ESM` (seen in lifeshifter), which reads like a broken test, not a wrong runtime.
- **PHP ^8.3** (`api.rnc/composer.json`; 8.4 locally). Valet is not used: `php artisan serve`.

## Starting a session

Run `/battoni-start-session` — it will ask which project(s) you're working on and load the right rules before anything else.

## Rule activation

- **During implementation** (files open): rules load automatically via glob patterns — no manual step needed.
- **During planning** (no files open yet): run `/battoni-start-session`.
