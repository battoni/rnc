---
outline: deep
title: The rnc Monorepo
---

# The rnc Monorepo

Everything battoni.dev builds lives in a single monorepo called **rnc**. It holds the bootstrap projects we start new client work from, the design-token source, and the shared AI tooling that keeps every project consistent.

> rnc is always used **as a whole**. Sub-projects are never forked off on their own — rules, commands, and conventions live at the root and apply across the board.

## The six sub-projects

| Project | Type | What it's for |
| --- | --- | --- |
| [Arcus](/projects/api.rnc) | Laravel API | Backend API bootstrap — the starting point for new APIs |
| [app.rnc](/projects/app.rnc) | Vue 3 SPA | Frontend bootstrap — the starting point for new web apps |
| [Vitrum](/projects/vitrum) | Astro site | Public / marketing website bootstrap |
| [Liquen](/projects/liquen) | Design tokens | Figma integration and design-token management |
| [Cortex](/projects/cortex) | AI knowledge | Changelog, prompts, and AI infrastructure docs |
| [Codelumen](/projects/codelumen) | VitePress | This handbook |

## How a new project starts

```text
1. Clone rnc
2. Delete the modules you won't use
3. Rename folders to match your project (e.g. api.rnc → api.myproject)
4. Run /battoni-setup-project   — maps all rules and commands to your folder names
5. Run /battoni-start-session   — loads the right rules before you begin
```

## The shared layers

Two layers sit above the sub-projects and apply to all of them:

- **[AI Tooling](/ai-tooling)** — the rules, skills, agents, hooks, and commands (Cortex) that activate automatically as you work.
- **[Workflow](/git-flow)** — how we run tasks day to day: Git/Notion flow, communication, checkpoints, and the review path.

## Where conventions live

The enforced conventions are **not** written here by hand — they live in `.claude/rules/` at the monorepo root and are mirrored into this site automatically. Each project's page links to its generated rule pages, so what you read here is always exactly what the tooling enforces.
