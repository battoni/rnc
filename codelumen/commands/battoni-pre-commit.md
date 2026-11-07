---
title: /battoni-pre-commit
outline: deep
---

::: info Generated page
This page is generated from `.claude/commands/battoni-pre-commit.md` — run it with `/battoni-pre-commit`. **Edit the source command, not this page.**
:::

# Pre-commit

Run every mechanical and convention check **after** the developer has finished their manual review, but **before** committing. This command commits nothing — it only verifies the branch is ready.

> Run from the rnc monorepo root.

---

## Step 1 — Mechanical checks

Run the Makefile gate — the local mirror of CI (`.github/workflows/ci.yml`):

```bash
make pre-commit
```

This runs, in order, and stops at the first failure — static checks first, because they fail in seconds:

1. `make check-app` — app.rnc: features catalogue current (`node scripts/build-features.mjs --check`), module aliases (`npm run check:aliases`), `npm run type-check`
2. `make check-api` — api.rnc, whole app: `vendor/bin/pint --test` and PHPStan (`vendor/bin/phpstan analyse`)
3. `make check-portal` — the docs portal's extracted data, gate tests and markdown lint; skipped when there is no `docs.rnc/`
4. `make check-cortex` — rnc only (skipped where `.cortex-sync.conf` exists): `sync.py --audit`, `test_sync.py`, `test_backport.py`, the engine and init proofs
5. `make sync-mirror-check` — every `.cursor` mirror identical to its `.claude` source
6. `make sanitize` — `npm run lint`, `npm run format`, `npm run sort-locales` in app.rnc. These **fix** files instead of only checking them, so a green run can still leave changes to commit
7. `make test-all` — app.rnc unit/integration (`test:unit:run`, a single run — never watch mode), app.rnc e2e (`test:e2e`), api.rnc `pest`

What CI runs and this does not: the portal and handbook **builds**, the landing's `npm test`, and `composer audit`.

If any step fails, **stop** and report what failed. Do not continue to the reviews until the tree is green.

## Step 2 — Convention reviews

With the mechanical checks green, run the AI convention reviews for whichever sub-projects the branch touched:

- If the branch changed any `app.rnc/**` Vue/TS files → run `/battoni-review-vue-conventions`, then `/battoni-review-design-conventions`.
- If the branch changed any `api.rnc/**` PHP files → run `/battoni-review-arcus-code`.

Use `git diff main...HEAD --stat` (or the project's base branch) to decide which reviews apply.

## Step 3 — Report

Summarise the outcome:

- ✅ / ❌ for `make pre-commit`, naming the step that failed
- ✅ / ❌ for each review that ran, with any violations found and fixed
- A clear go / no-go: is the branch ready for `/battoni-commit`?

---

## Rules

- **Never commit, stage, or push** — this command only verifies. Committing is `/battoni-commit`.
- If lint/format/locale steps modified files, leave them in the working tree for `/battoni-commit` to pick up — do not commit them here.
- If a review finds violations, fix them, then re-run the relevant checks before reporting go.
- Stop on the first failing mechanical step; do not mask failures.
