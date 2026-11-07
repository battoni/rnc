---
outline: deep
title: app.rnc
---

# app.rnc — Vue 3 SPA

app.rnc is the **frontend bootstrap** — the starting point for new web applications.

## Stack

- **Vue 3.5** with `<script setup lang="ts">` (Composition API)
- **TypeScript**, **PrimeVue** (auto-imported), **Tailwind CSS**
- **Pinia**, **Vue Router**, **Vue I18n**
- **Yup** validation via PrimeVue Forms (`@primevue/forms`)
- **ESLint + Prettier** auto-fix on save

## Key technical facts

- **No component imports** — `unplugin-vue-components` auto-imports everything from `src/modules/*`, `src/components/*`, `src/layouts/*`, plus all PrimeVue components.
- **Single-file theme** — change `src/styles/theme/colors.css` to retheme the whole app; semantic names (`primary`, `surface`, `ink`) propagate to PrimeVue tokens and Tailwind utilities.
- **RULES.md chains** — every component folder up to `src/` can carry a `RULES.md`; the closest one wins and is injected automatically before edits.

## Conventions (generated from the rules)

| Page | Covers |
| --- | --- |
| [Vue Checklist](/rules/app.rnc/01-vue-checklist) | The mandatory pre-flight checklist |
| [Vue Imports](/rules/app.rnc/02-vue-imports) | Import ordering + module-alias rules |
| [Vue Script](/rules/app.rnc/03-vue-script) | Script structure, reactivity order, patterns |
| [Vue Template](/rules/app.rnc/04-vue-template) | Template formatting, attribute order |
| [Module Conventions](/rules/app.rnc/05-module-conventions) | enums / constants / types / services split |
| [Design System](/rules/app.rnc/06-design-system) | Typography, spacing, radius, buttons |
| [View Patterns](/rules/app.rnc/07-view-patterns) | List/detail view structure, dialogs |
| [Rules Auto Injection](/rules/app.rnc/08-rules-auto-injection) | How `RULES.md` injection works |

See also the [Design System](/design-system) reference and the [Recipes & Snippets](/intro-recipes-snippets).

## Skills that load for app.rnc

- `celer-advanced-patterns` — new modules, composables, complex forms
- `celer-testing` — test authoring
