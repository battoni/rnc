---
outline: deep
title: Arcus
---

# Arcus — Laravel API

Arcus is the **backend API bootstrap** — the starting point for new battoni.dev APIs.

## Stack

- **Laravel** + **PHP 8.4**
- DDD-style module structure (bounded contexts under `app/Modules/`)
- Spatie Data DTOs, Eloquent API Resources, role-based permissions
- **Pest** for testing, **Pint** for formatting (runs automatically on edit)

## Architecture at a glance

The canonical request flow:

```text
ROUTE → REQUEST → CONTROLLER → DTO → SERVICE → REPOSITORY → ApiResponse + Resource
```

Controllers stay thin, services hold business logic, repositories own data access with semantic method names (`findRoleById`, `syncRolePermissions`). Mutating endpoints return the affected resource so the frontend can update state granularly.

📐 **Full rules:** [API Architecture](/rules/api.rnc/api-architecture)

## Skills that load for Arcus

When working in api.rnc, Claude Code loads these on demand:

- `arcus-laravel-best-practices` — Laravel PHP patterns
- `arcus-pest-testing` — test authoring
- `arcus-tailwindcss` — Tailwind in Blade templates

## Conventions

- [API Architecture](/rules/api.rnc/api-architecture) — request flow, module structure, factories, seeders
- [Shared Conventions](/rules/shared/conventions) — git, commits, PRs, code hygiene
