---
version: 1.1.0
origin: pendulum
based-on: 1.1.0
---

# RULES — MSearch (MSearchMultiselect)

> For AI agents. Last updated: 2026-05-22.

## Purpose

A multi-select search filter rendered as an `InputGroup` with an icon addon. Allows selecting multiple filter values displayed as chips.

## Intentional Decisions

- **File is `MSearchMultiselect.vue` inside the `MSearch` folder**: the component is a MultiSelect, not a text input. The folder name is intentionally generic to allow future variants.
- **`filter` prop is always on**: the dropdown is searchable by default.
- **`display="chip"`**: selected values render as chips inside the input. Do not change to `"comma"`.
- **`border-none shadow`**: same floating look as `MOrderBy` — no border, shadow instead.

## Prop & Emit Contract

- `options`: list of `Option` items (`{ label, value }` from `@Interfaces`).
- `v-model:search`: bound to the MultiSelect model. Despite the `string` type annotation, MultiSelect returns an array of selected `optionValue`s when multiple items are selected. Treat this as `string | string[]` at the call site.

## Do Not

- **Do not replace with a text `InputText`** without renaming the component — the current contract is multi-value selection.
