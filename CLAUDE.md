# rnc — Monorepo Context

This is the rnc monorepo. It contains multiple independent sub-projects and a shared AI knowledge layer.

## Sub-projects

| Project | Type | Description |
| --- | --- | --- |
| `api.rnc/` | Laravel API | Backend API bootstrap — starting point for new APIs |
| `app.rnc/` | Vue 3 SPA | Frontend bootstrap — starting point for new web apps |
| `codelumen/` | VitePress | Code conventions documentation site |
| `vitrum/` | Astro | Public/marketing website bootstrap |
| `liquen/` | Design tokens | Figma integration and design token management |
| `cortex/` | AI knowledge | Changelog, prompts, and AI infrastructure documentation |
| `docs.rnc/` | VitePress | rnc's own documentation portal (User / Developer depth) — `SKIP` in the sync manifest, never propagates |
| `public.battoni.dev/` | Astro | battoni.dev's public landing site and the reference template of the `landing-page` skill — `SKIP`, never propagates |
| `scripts/` | Shell | Root tooling: `pint-changed.sh` / `phpstan-changed.sh` run Pint and PHPStan on the api.rnc files changed on the branch |

## AI tooling

| Layer | What it does |
| --- | --- |
| `CLAUDE.md` | Always-loaded project context (`.cursor/rules/project-context.mdc` carries the same body for Cursor) |
| `.claude/rules/` | `arcus-api-architecture`, `celer-01` … `celer-09`, `shared-conventions` |
| `.claude/skills/` | Demand-loaded specializations (12): `arcus-external-integrations` (payment/messaging providers, webhooks, idempotency), `arcus-laravel-best-practices`, `arcus-pest-testing`, `arcus-tailwindcss`, `celer-advanced-patterns`, `celer-testing` (Vitest + Playwright), `cortex-backport` (bring a downstream feature back), `cortex-sync` (RULES.md/CHANGELOG triad and the sync engine), `docs-portal` (dual-audience VitePress docs portal), `landing-page` (build/review Astro landing pages — recipe: `codelumen/landing-page-recipe.md` while `codelumen/` is kept, template: `public.battoni.dev/`), `midnight-oil` (unattended phase-by-phase build), `phase-review` (review a plan or a built phase) |
| `.claude/commands/` | The `/battoni-*` commands (21) — start with `/battoni-start-session`; a feature runs `/battoni-intent` → `/battoni-spec` → `/battoni-plan` → `/battoni-start-session <id>` per phase → `/battoni-verify`, or `/battoni-run <id>` to build and verify every phase with nobody there (reference: `.claude/feature-flow.md`, folders in `specs/`); the reviews are `/battoni-review-vue-conventions`, `/battoni-review-design-conventions` and `/battoni-review-arcus-code`, and `/battoni-pre-commit` runs them before a commit |
| `.claude/agents/` | `code-reviewer` (read-only PR review), `security-auditor` (security scan), `battoni-reader` (Haiku, read-only fetch) — who gets which work: `.claude/delegation-policy.md` |
| `.claude/hooks/` | `pre-edit-rules.sh` injects the `RULES.md` chain before an Edit/Write in `app.rnc/src/`; `pre-bash-guard.sh` blocks force pushes, `rm -rf /` or `~`, `DROP TABLE` and writes to a raw disk; `post-edit.sh` runs Pint on the edited api.rnc PHP file and ESLint on the edited app.rnc Vue/TS file. Tests: `cortex/hooks/test_claude_hooks.py` |
| `.cursor/rules/` | Cursor mirrors in `.mdc` format — every rule and command, and the skills `celer-testing`, `cortex-backport`, `cortex-sync` and `docs-portal` (the other skills have no Cursor counterpart) — plus `celer-folder-*` and the always-loaded `project-context.mdc`. Bodies stay identical to `.claude`: `make sync-mirror-check` |

## Toolchain

- **Node 22** (`.nvmrc`). On Node 20 the Vitest suite dies with `ERR_REQUIRE_ESM` (seen in lifeshifter), which reads like a broken test, not a wrong runtime.
- **PHP ^8.3** (`api.rnc/composer.json`).

## Starting a session

Run `/battoni-start-session` — it will ask which project(s) you're working on and load the right rules before anything else.

## Rule activation

- **During implementation** (files open): rules load automatically via glob patterns — no manual step needed.
- **During planning** (no files open yet): run `/battoni-start-session`.
