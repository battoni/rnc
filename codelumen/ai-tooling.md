---
outline: deep
title: AI Tooling
---

# AI Tooling

rnc ships with an AI layer that keeps every project consistent without anyone memorising the rules. It works in both **Claude Code** (as `/command-name`) and **Cursor**. The knowledge and history behind it live in the `cortex/` sub-project — see the [Cortex page](/projects/cortex).

## How it fits together

| Layer | What it does |
| --- | --- |
| `CLAUDE.md` | Always-loaded project context |
| `.claude/rules/` | Rules that auto-load by file path (glob) — api.rnc, app.rnc, shared |
| `.claude/skills/` | Demand-loaded specializations, loaded only when the task needs them |
| `.claude/commands/` | Slash commands like `/battoni-start-session`, `/battoni-setup-project` |
| `.claude/agents/` | Isolated read-only helpers (`code-reviewer`, `security-auditor`) |
| `.claude/hooks/` | Auto-formatting and guards that run on edit / before bash |
| `.cursor/rules/` | The same rules in Cursor's `.mdc` format |

## Rules

Rules activate **automatically** when you open or edit a matching file — no manual loading during implementation. When you're planning before any files are open, run `/battoni-start-session` to load the right set.

The enforced rules are mirrored into this handbook so they never drift:

- **Arcus** — [API Architecture](/rules/api.rnc/api-architecture)
- **app.rnc** — [Vue Checklist](/rules/app.rnc/01-vue-checklist) and the rest of the `celer-*` set
- **Shared** — [Shared Conventions](/rules/shared/conventions)

Each rule has one **canonical source** in `.claude/rules/` (at the monorepo root). The `.cursor/rules/` mirror and this handbook are both generated from it — so edit the source rule, never the mirror or the page. See [Keeping Claude and Cursor in sync](#keeping-claude-and-cursor-in-sync).

## Skills (Claude Code)

Demand-loaded specializations that keep the context window lean:

| Skill | Loads when |
| --- | --- |
| `arcus-laravel-best-practices` | Writing or refactoring Laravel PHP in api.rnc |
| `arcus-pest-testing` | Writing or editing an api.rnc test file |
| `arcus-tailwindcss` | Working with Tailwind in api.rnc Blade templates |
| `celer-advanced-patterns` | Building a new module, composable, or complex form in app.rnc |
| `celer-testing` | Writing or editing app.rnc tests |
| `docs-portal` | Building a dual-audience (User/Developer) VitePress documentation portal for a project |

## Agents (Claude Code)

Isolated, limited-tool helpers for focused work that shouldn't pollute the main conversation:

| Agent | What it does |
| --- | --- |
| `code-reviewer` | Read-only review of a branch/PR — structured violation report, never edits |
| `security-auditor` | Security scan — OWASP top 10, auth gaps, exposed secrets, never edits |

## Hooks (Claude Code)

Run automatically, no trigger needed:

- **Formatting** — Pint on api.rnc PHP edits; ESLint on app.rnc Vue/TS edits.
- **RULES.md injection** — before editing a app.rnc file, the folder's `RULES.md` chain (up to `src/`) is injected so local rules win.
- **Bash guard** — before any bash command, dangerous patterns (`rm -rf /`, `rm -rf ~`, `git push --force`, `drop table`, …) are blocked.

## Commands

Setup and session:

| Command | What it does |
| --- | --- |
| `/battoni-setup-project` | One-time setup after cloning — transforms the bootstrap into your project's names and trims what you don't use |
| `/battoni-start-session` | Loads the right rules before planning begins |
| `/battoni-explain-rnc` | Full end-to-end overview of the AI setup for a new developer |

Commit and PR flow:

| Command | What it does |
| --- | --- |
| `/battoni-commit` | Stages and commits all changes as conventional commits, grouped by concern, ordered bottom-up |
| `/battoni-create-pr` | Generates a PR title and description for the current branch |
| `/battoni-publish-pr` | Pushes the branch and opens a draft PR on GitHub using the `/battoni-create-pr` output |

Review:

| Command | What it does |
| --- | --- |
| `/battoni-review-vue-conventions` | Reviews app.rnc Vue/TS changes on the branch and fixes convention violations |
| `/battoni-review-arcus-code` | Reviews api.rnc PHP changes on the branch and fixes convention violations |
| `/battoni-review-design-conventions` | Reviews app.rnc changes for design-system violations (runs in plan mode) |

Rule generation:

| Command | What it does |
| --- | --- |
| `/battoni-generate-api-rules` | Generates a `RULES.md` for a unit in `api.rnc/` |
| `/battoni-generate-component-rules` | Generates a `RULES.md` for a folder in `app.rnc/src/` |

## Adding or updating a rule

There are two kinds of rules.

**Project-wide rules** (`.claude/rules/`) — the cross-cutting conventions mirrored into this handbook:

1. Edit the canonical rule in `.claude/rules/`.
2. Run `make sync-mirror` to regenerate the `.cursor/rules/` mirror.
3. Add an entry to `cortex/CHANGELOG.md`.
4. Commit both sides. This handbook regenerates from the rule on the next build — no separate docs edit needed.

**Folder / unit rules** (`RULES.md`) — rules scoped to a single component folder or API unit. Don't hand-write these; generate them:

- `/battoni-generate-component-rules` — creates a `RULES.md` for a folder in `app.rnc/src/`.
- `/battoni-generate-api-rules` — creates a `RULES.md` for a unit in `api.rnc/`.

## Keeping Claude and Cursor in sync

Every rule and command exists in two formats — `.claude/` for Claude Code and `.cursor/` for Cursor — and they must stay **identical**. `.claude` is canonical; `.cursor` is a generated mirror (only the frontmatter differs).

A small tool enforces this:

| Command | What it does |
| --- | --- |
| `make sync-mirror` | Regenerates every `.cursor/rules/*.mdc` body from its canonical `.claude` source |
| `make sync-mirror-check` | Fails if any mirror has drifted — read-only, no writes |

`make sync-mirror-check` runs inside `make pre-commit` **and** as a CI job, so a drifted mirror can never reach `main`. After editing any rule or command in `.claude/`, run `make sync-mirror` and commit both sides.
