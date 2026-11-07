---
version: 1.1.0
origin: pendulum
based-on: 1.1.0
---

# RULES — MOrderBy

> For AI agents. Last updated: 2026-05-22.

## Purpose

A single-select sort control rendered as an `InputGroup` with an icon addon. Emits the selected sort key via `v-model:orderBy`.

## Intentional Decisions

- **`showClear` is always on**: users can reset the sort order. Do not remove it.
- **`border-none shadow`** on both the addon and the Select: the visual border is replaced by a shadow for a floating look. Do not add a border.
- **`optionLabel`/`optionValue` are fixed**: options must follow the `Option` shape from `@Interfaces` (`{ label: string; value: string }`).

## Prop & Emit Contract

- `options`: list of `Option` items (`{ label, value }` from `@Interfaces`). The `value` field is what gets bound to the model.
- `v-model:orderBy`: the currently selected sort key (a `string` from `Option.value`), or `undefined` when cleared.

## Do Not

- **Do not rename the model** from `orderBy` — call sites bind `v-model:orderBy`.
