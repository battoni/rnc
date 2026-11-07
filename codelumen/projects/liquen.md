---
outline: deep
title: Liquen
---

# Liquen — Design Tokens

Liquen is the **design-token source** and Figma integration that keeps visual decisions consistent across every battoni.dev project.

## What's inside

```text
liquen/
  tokens/
    primitives/    # Raw color, spacing, typography values
    semantic/      # Named decisions (primary, surface, text-muted)
    components/    # Component-level overrides
  fonts/           # Font files
  logo/            # Logo assets
  tokens-studio.json   # Tokens Studio export — source of truth
```

## Token layers

1. **Primitives** — raw values (colors, sizes, font stacks)
2. **Semantic** — named decisions (`primary`, `surface`, `text-muted`)
3. **Components** — component-specific overrides

## Workflow

Tokens are managed in **Tokens Studio** (the Figma plugin) and synced via the `github-token` config. The exported `tokens-studio.json` is the source of truth for all design decisions, which then flow into app.rnc's theme (`src/styles/theme/colors.css`) and the [Design System](/design-system).

## Conventions

Liquen follows the [Shared Conventions](/rules/shared/conventions). It has no code rule set of its own — it's a token pipeline rather than an application.
