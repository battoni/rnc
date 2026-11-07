---
outline: deep
title: Codelumen
---

# Codelumen — This Handbook

Codelumen is the **internal handbook** you're reading now — a VitePress site that documents how battoni.dev builds: the projects, the workflow, and the enforced conventions.

## How it stays in sync

The convention pages are **not hand-written**. A generator reads the canonical rule files at the monorepo root and produces one page per rule:

```text
.claude/rules/*.md  ──▶  scripts/generate-rules.mjs  ──▶  codelumen/rules/<project>/*.md
                                                          + .vitepress/generated/rules-sidebar.json
```

This runs automatically before `docs:dev` and `docs:build` (via the `predocs:*` npm scripts), so the site can never drift from what the tooling enforces. The generator has **no dependencies** and a deploy guard: if the rules source isn't reachable at build time, it keeps the already-committed output.

## Add or change a rule

Don't edit pages under `rules/` — they're regenerated and your change would be lost. Instead:

1. Edit the source rule in `.claude/rules/` (and its `.cursor/rules/` mirror).
2. Run `npm run docs:gen` (or just `npm run docs:dev`).
3. Commit the source rule **and** the regenerated pages.

## Authored vs generated

- **Generated:** everything under `rules/` (Arcus, app.rnc, Shared).
- **Authored by hand:** the project overviews, the [monorepo map](/monorepo), [AI tooling](/ai-tooling), workflow pages, and the recipes.

## Conventions

Codelumen follows the [Shared Conventions](/rules/shared/conventions).
