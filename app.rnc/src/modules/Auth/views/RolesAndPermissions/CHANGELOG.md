# Changelog — RolesAndPermissionsView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.2.0 — 2026-09-21

### Fixed

- A refused save of the matrix said "something went wrong": the catch sent every failure to the red
  toast, so the 422 naming the rule (`cannotGrantUnheldPermission`, `administratorKeepsEveryKey`) was
  never read. It is now a `warn` toast with the translated key, as `UsersView` does; anything else
  is still the error toast (Leonardo's QA on PR #39). Spec seen red with the branch removed.

## 2.1.2 — 2026-09-17

### Removed

- `interfaces.ts` in this folder. It only re-exported the module types for `MPermissionPanel`, which now imports them from `@AuthModule` (CEL-12).

## 2.1.1 — 2026-09-17

### Fixed

- Write controls follow `permissions.update`, the key every write route asks for (`$rolesWrite` in the API). The route opens on `permissions.read`, and a reader was offered "New profile", edit, delete and save — each one a 403. Now the create CTA is not rendered, `MPermissionToolbar` gets `:canUpdate` and the panels' switches are held (`arePanelsLocked`). Covered by two cases in the integration spec.

### Changed

- `PermissionGroup` and `Profile` come from the module's `interfaces.ts`.
- **Retired** from `RULES.md`: "Two `ConfirmDialog` / `ConfirmPopup` groups". The blocked-delete notice is an `MMainDialog` since the ConfirmDialog was removed, and the bullet described a group that no longer exists.

## 2.1.0 — 2026-09-16

### Fixed

- Both named routes declare `meta: { permission: 'permissions.read' }`, so the guard closes the screen for an account without it instead of only hiding the nav link (lifeshifter-459). Covered by `routes.spec.ts` and an e2e case in `e2e/roles/roles.spec.ts`.

## 2.0.0 — 2026-09-16

### Changed

- **Breaking:** `Profile.id` is a `number`. The active tab is the role id as a number, `0` (`NO_ACTIVE_ROLE`) when none is selected — PrimeVue `Tabs` takes no `null`. The integration spec uses numeric role ids (from lifeshifter-269).

## 1.2.3 — 2026-09-15

### Fixed

- “Novo Perfil” moved to `#primaryAction` as `APageHeaderAction` (icon-only on phone). A labeled
  button in `#actions` crushed the long title at 360px (“Perfis e Permissõ…”).

## 1.2.2 — 2026-09-14

### Fixed

- "This role is still in use" is an `MMainDialog` listing who uses it, not a `ConfirmDialog` with a fully custom `#container` — nothing was being confirmed, the delete was refused (from lifeshifter).
- `<aside class="contents">` for the overlays. The aside renders nothing in flow, but as a box it is still a flex child of `TheLayout`'s content column, whose `gap-8` reserved 32px under the page for it (from lifeshifter).

### Added

- `PermissionGroup.isHeld` on a role's groups, read by the profile form to pre-select what the role holds.
- `data-testid="add-profile"` on the header action, so the e2e spec stops matching the button by its label (from lifeshifter).
- The "roles fetch fails" integration test asserts the error toast. It passed on `document.body.innerHTML.includes('error')`, which the `ConfirmDialog` stub's `group="delete-error"` satisfied on its own; removing the dialog made it fail for the first time.

## 1.2.1 — 2026-09-10

### Fixed

- Saving no longer replaces the page. `loading` drives the skeleton, so raising it on save tore
  the tabs down and rebuilt every panel for a request that changes nothing on screen — and the
  page that came back had closed every group the moderator had opened to make the edit. Saving is
  now its own state, and the forced collapse is gone.

## 1.2.0 — 2026-06-13

### Changed

- Remove the unused ThePageHeader icon.

## 1.1.0 — 2026-05-30

### Added

- Integration tests (`RolesAndPermissionsView.view.integration.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
