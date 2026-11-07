---
version: 2.1.0
origin: pendulum
based-on: 2.1.0
---

# RULES — MPermissionToolbar

> For AI agents. Last updated: 2026-09-17.

## Purpose

Toolbar row above the permission panels for a role tab. Controls expand/collapse all, and surfaces save, edit, and delete actions.

## Intentional Decisions

- **`v-model` (no name) controls expand/collapse all**: the model is a `boolean` — `true` = all expanded, `false` = all collapsed. The parent view owns this state.
- **Delete is disabled when `canDeleteProfile` is `false`**: the administrator role cannot be deleted. A tooltip explains why.
- **Delete passes `event` (not just the click signal)**: `@click="(event) => emit('onDeleteProfileRequest', event)"` — the event is needed by the parent to position the `ConfirmPopup` anchor.
- **`tab` prop is the role ID**: used only to generate a unique `inputId` for the expand/collapse ToggleSwitch (`toggle-all-panels-${tab}`) — `inputId`, not `id`, so the id lands on the real `<input>` the label points at. Prevents `id` collisions when multiple tabs render simultaneously.

## Prop & Emit Contract

- `v-model` (boolean): expand/collapse all state. Two-way binding with the parent view.
- `loading`: disables all buttons — delete included — and the expand/collapse switch.
- `tab`: role ID (a number), used only for unique element IDs.
- `canDeleteProfile`: `false` disables the delete button and updates its tooltip.
- `canUpdate` (default `true`): `false` renders no save, edit or delete — the view passes `permissions.update`. A reader keeps the expand/collapse switch.
- `@onSavePermissionsRequest`: fires when Save is clicked.
- `@onEditProfileRequest`: fires when Edit is clicked.
- `@onDeleteProfileRequest([event])`: fires when Delete is clicked; passes the click event for popup anchoring.

## Do Not

- **Do not remove the `event` argument from the delete button handler** — the parent needs it for `ConfirmPopup` positioning.
