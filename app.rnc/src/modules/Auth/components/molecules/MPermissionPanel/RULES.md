---
version: 1.2.1
origin: pendulum
based-on: 1.2.1
---

# RULES — MPermissionPanel

> For AI agents. Last updated: 2026-09-17.

## Purpose

A collapsible Panel showing all permissions in a single `PermissionGroup`, with individual toggle switches and a "select all / deselect all" toggle.

## Intentional Decisions

- **`permissionGroup.permissions` items are mutated directly** via `permission.value = value` in the `allSelected` setter: the permissions array is passed by reference from the parent view's reactive state. This direct mutation is intentional — the view's `permissionGroups` ref is the single source of truth.
- **`allSelected` is a writable computed**: the getter checks if every permission is active; the setter applies a boolean to all. This drives the "activate/deselect all" ToggleSwitch.
- **`collapsed` defaults to `true`**: panels start collapsed. The parent controls collapse state via `panelStates` record and passes it as `v-model:collapsed`.
- **`allPanelsExpanded` prop** drives the ToggleSwitch tooltip text but does NOT control collapse: collapse is managed by `v-model:collapsed` from the parent. `allPanelsExpanded` is read-only context.
- **`PermissionGroup` comes from `@AuthModule`** (a type-only import, erased at build, so it loads nothing). It is defined in the module's `interfaces.ts`.
  **Retired:** "`PermissionGroup` is local to `RolesAndPermissions/views` … not in the module barrel". The type moved to the module's `interfaces.ts` (CEL-12), and the view's re-export file was deleted once this was its last importer.
- **A permission switch is named by its visible `<label for>` alone.** No `ariaLabel`: it overrides the label, and the one this had appended an English " permission" that a Portuguese screen reader read out too. The select-all switch has no visible label, so it keeps its translated `ariaLabel`.
- **The group icon is `bg-primary-100 text-primary-950`**, the pair the nav uses for its active item — not the raw `ink` ramp.
- **Switch ids go through `inputId`**, never `id`: on `ToggleSwitch` the `id` lands on the root `<div>`, and the `<label for>` beside it then toggles nothing.

## Prop & Emit Contract

- `permissionGroup`: the group to render, including its `permissions` array (items have a mutable `value` field).
- `allPanelsExpanded`: read-only boolean for tooltip context. Does not control collapse.
- `loading`: disables all toggle switches.
- `v-model:collapsed` (via `defineModel`): controls the panel's collapsed state.

## Do Not

- **Do not copy permissions before passing** — the parent expects direct mutation.
- **Do not import `PermissionGroup` by a path into a view** — it comes from `@AuthModule`.
- **Do not give a permission switch an `ariaLabel`** — its `<label for>` is the name.
