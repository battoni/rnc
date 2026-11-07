---
version: 2.2.0
origin: pendulum
based-on: 2.2.0
---

# RULES — RolesAndPermissionsView

> For AI agents. Last updated: 2026-09-17.

## Purpose

Full role management page: tabbed role list, permission panels, add/edit role dialog, and delete confirm popup.

## Intentional Decisions

- **`panelStates` record** (`Record<string, boolean>`): keyed by permission group ID, stores the collapsed state of each `MPermissionPanel`. This is intentional — collapsed state is tracked per-group, not per-panel component instance.
- **`panelResetKey`** is incremented to force-remount `MPermissionPanel` instances: PrimeVue `Panel` does not reset collapsed state when its `v-model:collapsed` prop changes externally. The key forces a full remount to apply the new state.
- **`allPanelsExpandedModel`** is a writable computed that sets all `panelStates` values: the getter checks all states; the setter applies a boolean to all. This drives `MPermissionToolbar`'s expand/collapse switch.
- **`setPermissionGroups` initializes `panelStates`** to all `true` (collapsed): called on initial load and after role create/edit/switch.
- **Delete: a `ConfirmPopup` to confirm, an `MMainDialog` to refuse.** `'delete'` is the `MConfirmPopup` group for the standard confirm; a role still assigned to people opens an `MMainDialog` listing them, because nothing is being confirmed and the list can run long.
  **Retired:** "Two `ConfirmDialog` / `ConfirmPopup` groups … `'delete-error'` uses PrimeVue's `ConfirmDialog`" — that dialog was replaced by the `MMainDialog`, and the group no longer exists.
- **Every write control follows `permissions.update`** (`canUpdate`): the create CTA, `MPermissionToolbar`'s save/edit/delete (`:canUpdate`) and the panel switches (`arePanelsLocked` = busy OR no `permissions.update`). The API asks that key for every write (`$rolesWrite`); the route only asks `permissions.read`, so without this a reader was offered four actions that could only fail.
- **`onSavePermissionsRequest` calls `userStore.fetchMe()`** after save: the current user's permissions may have changed if they edited their own role.
- **`MAddEditProfileForm` is keyed on `editingProfile?.id ?? 'new'`**: forces form reset when switching between create/edit modes.
- **`activeRole` is computed from `activeTab`** (the tab value = the numeric role ID; `NO_ACTIVE_ROLE` = `0` when none): do not store `activeRole` as a separate ref.
- **`isFooterless` on `MMainDialog`**: the form has its own sticky footer buttons; no default Cancel/Submit footer needed.
- **Create-profile CTA is `#primaryAction` via `APageHeaderAction`**: icon-only below `md` so the
  long title (“Perfis e Permissões”) + tip fit one phone row without truncating. Do not put a
  labeled create button in `#actions` on this screen.

- **The route closes on `meta.permission: 'permissions.read'`** (both named routes). The nav only
  hid the link; typing the address opened the screen for any signed-in account. The API reads roles
  for `users.create`/`users.update` too (the user form needs the list), but this screen is where
  permissions are read and changed, so it asks for the permissions key. `routes.spec.ts` runs both
  routes through `attachAuthGuard`; the e2e `mockUser` carries `permissions.read`/`permissions.update`.

## Dependencies & Context

- **`PermissionGroup` and `Profile`** live in the module's `interfaces.ts`. This folder has no `interfaces.ts` any more; do not bring one back — a type another unit needs goes in the module's file.
- **`MPermissionPanel`** mutates `permissionGroup.permissions[n].value` directly — the `permissionGroups` ref in this view IS the source of truth.

## A refused save is a warning, not a failure

- **A 422 from saving the matrix shows the rule it broke** — `translateError` of the first key in
  `errors`, as a `warn` toast — and never the red "something went wrong". The API refuses a grant
  the moderator does not hold (`cannotGrantUnheldPermission`) and any key taken off the
  Administrator (`administratorKeepsEveryKey`); both are information. Only what is not a 422 is the
  error toast (celer-07).

## Do Not

- **Do not add a second source for permission state** — mutations flow through `permissionGroups.value` directly.
- **Do not turn the blocked-delete `MMainDialog` back into a confirm** — nothing is being confirmed there.
