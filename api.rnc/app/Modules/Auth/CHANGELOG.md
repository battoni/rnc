# Changelog — Auth Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.18.1 — 2026-11-14
- `RoleService::create` recognises MySQL 8's unique-violation message, which names the index (`roles.roles_name_unique`,
  `roles.roles_slug_unique`) where SQLite names the column: a name race was a 500 and a slug collision never moved to the
  next slug on MySQL (rnc-4 F7b). Tested by `tests/Feature/Auth/AuthHardeningTest` (`a role refusal`) under `make test-mysql`.

## 2.18.0 — 2026-11-14
- `UserRepository::findAllFirmUsers` — every login of a firm, active or not, for `firm:offboard`'s export (rnc-4 F9a, R40). Tested by `tests/Feature/Firm/OffboardFirmCommandTest`.

## 2.17.0 — 2026-11-14
- `auth/me`'s `platform.view_as` also carries the viewed firm's opt-in `modules` (`FirmModules::enabled`), so the back office offers them during a view-as (rnc-4 F7, W93). Tested by `tests/Feature/Platform/ViewAsTest`.

## 2.16.0 — 2026-11-14
- `auth/logout` closes the session's open view-as with reason `logout`, and `auth/me`'s `platform.view_as` answers the viewed firm (rnc-4 F4c, R26). Tested by `tests/Feature/Platform/ViewAsTest`.

## 2.15.0 — 2026-11-14

### Added

- The firm's manager runs its team (rnc-4 F5a, R31, AM2): `Rules/TeamRole` (no `operador` or
  `parceiro` from a firm member), `is_specialist` on `StoreUserRequest` / `UpdateUserRequest` /
  `UserUpdateDTO`, `UserService` starts a newly flagged specialist (Booking's `SpecialistService`),
  `ResolvesActorPermissions::actorIsPlatformOperator`; the `firm` permission group (`firm.settings`).
  Tested by `tests/Feature/Auth/FirmUsersTest`, `SeededRolesTest`.

## 2.14.0 — 2026-11-14
- The platform console's keys (rnc-4 F4a, R24): `platform.firms` and `platform.consent` in a `platform` group; `RoleService::updatePermissions` refuses either to any role but the administrator's (422 `rolesAndPermissions.operatorOnlyKey`, `OPERATOR_ONLY_KEY_PREFIX`). `UserService::resetPassword()` (new password, every session ends) and `findByUsername()` for the console's manager login. Tested by `tests/Feature/Access/Phase4AccessTest`.

## 2.13.0 — 2026-11-14
- The sign-in answers carry `firm` and `platform` (rnc-4 F3c, R20): `POST auth/login` and `auth/phone/verify` answer `UserResource::withPlatform()` like `auth/me`, so the SPA knows the firm's modules from the first screen. Tested in `tests/Feature/Auth/MeFirmTest`.

## 2.12.0 — 2026-11-14
- `GET auth/me` answers `firm` (slug, name, look, opt-in `modules`; null for a firm-less account) and `platform: {name, is_operator, view_as}` (rnc-4 F3a, R20). Tested by `tests/Feature/Auth/MeFirmTest`.

## 2.11.0 — 2026-11-14

### Added

- `GET auth/me` carries `platform.name`, the product's name (rnc-4 F2a, R9), through `UserResource::withPlatform()`; a user in a list does not. Tested by `tests/Feature/Firm/PublicFirmBrandTest`.

## 2.10.0 — 2026-11-13

### Changed

- rnc-4 F1a (R4, R7, D5, D21; plan AM2, AM8): the password login, the phone code and the QA login-as refuse an account of another firm like a wrong password / wrong code (`FirmResolver::belongsTo`); `UserRepository` scopes the list, support names, phone lookup and `findFirmUserById` by the current firm and eager-loads `firm`; `UserService::create` stamps the current firm and `delete` deletes nothing outside it; self-registration answers 404 unless `registration.enabled`. Tests: `LoginFirmTest`, `UsersFirmScopeTest`, `RegistrationClosedTest`, `StatefulHostTest`.

## 2.9.0 — 2026-09-22

### Added

- `Helpers/user-exclusions.local.php` and `PermissionGroupsHelper::getLocalUserExclusions()`: the
  keys a project denies to the seeded member, LOCAL, the member's twin of `manager-exclusions`.
  Back-ported from largatudoevai (Leonardo's QA, 2026-09-18), where the catalog groups live in
  `permission-groups.local.php` and the member received their create and delete keys with no way to
  withhold them but an edit to the synced seeder. Tests: `UserLocalExclusionsTest`.

## 2.8.0 — 2026-09-21

### Fixed

- Nobody edits, archives or deletes an account that holds more than they do (a key the target has
  and the actor lacks). The grant cap stopped anyone handing out a key they lack, but not from taking
  power away from someone above them: a manager without `design.read` could deny it on the
  administrator's own row (a personal override beats the role), move them to a smaller profile or
  deactivate them, and then nobody held the key to put it back. 422 `users.cannotManageHigherAccount`
  on `id`. Decided by the owner on 2026-09-21. `UserTest` covers the four edits, archive and delete,
  and that a peer and your own row stay editable.

## 2.7.0 — 2026-09-21

### Fixed

- The administrator's matrix can no longer lose a key. Revoking there was open to anybody holding
  `permissions.update`, and under the grant cap a key that left the root could never be handed back
  — a manager without `design.read` could untick it and lock the design surface for everybody,
  the administrator included (Leonardo's QA on PR #39). A revoke on that role is now a 422
  (`rolesAndPermissions.administratorKeepsEveryKey`) for everybody; granting TO it is exempt from
  the cap; and every save writes the whole catalogue back, which heals a matrix already left
  incomplete. `RoleTest` covers the refusal, the recovery and the other roles' revokes.

## 2.6.1 — 2026-09-21

### Fixed

- A waiting registration is never overwritten, in either verification mode (see `Services` 2.3.2).
- `auth/register` and `auth/email/code` are throttled by e-mail as well as by address (three a
  minute per inbox). By address alone, a few machines could keep re-sending codes to one person —
  each resend replacing the code they were about to type. Both covered in `RegistrationTest`, and
  both seen red with the fix removed.

## 2.6.0 — 2026-09-20

### Fixed

- `users.create` / `users.update` stopped being a superuser. Whoever could edit an account could
  grant it — or themselves, on their own row — any key in the matrix, `permissions.update`
  included, or simply assign the administrator role. Requests now refuse a grant the actor does not
  hold and a role that grants more than they hold, and the same ceiling applies to a role's matrix
  (ARC-03). `UserTest` and `RoleTest` cover both doors and the revoke that stays allowed.

## 2.5.1 — 2026-09-20

### Fixed

- A second registration for an address that is already waiting for its code no longer overwrites the
  pending row. It re-issues the code to the address and leaves the stored name, document and
  password alone: overwriting let a stranger register somebody else's e-mail with their own
  password, and the code that reached the victim created an account the stranger knew the password
  to (ARC-05). The response is unchanged, so the endpoint still says nothing about who is
  registering. `RegistrationTest` covers it, and fails with the guard removed.

## 2.5.0 — 2026-09-17

### Added

- `Rules/DeclaredPermissionKeys`: one validation rule for every permission payload (role matrix, user overrides); an undeclared key is a 422 with `rolesAndPermissions.unknownPermission` (review ARC-02).
- `Exceptions/RoleDeletionException`: the administrator role and a role in use are refused by the service, rendered as 403 / 409 with an i18n key (review ARC-18).
- `pending_registrations.attempts` (edited into the create migration): the fifth wrong e-mail code burns it (review ARC-04).
- Named limiters `registration-verify` (IP + e-mail) and `support-names` (IP); `REGISTRATION_ATTEMPTS_PER_MINUTE` replaces the literal (review ARC-04, ARC-21, F1).
- `tests/Feature/Auth/AuthHardeningTest.php`.

### Changed

- `auth/users/check-username` asks for `users.create` or `users.update` (review ARC-14).
- `Models/LoginCode` documents its attribute types, which clears the module's last PHPStan error.

## 2.4.0 — 2026-09-16

### Added

- `Helpers/manager-exclusions.local.php` (LOCAL, empty in rnc) and `PermissionGroupsHelper::getLocalManagerExclusions()`: the keys a project denies to the seeded Manager. `$managerExclusionsPath` is a public static so a test points it at a fixture instead of writing the project's file (from largatudoevai-016).

### Changed

- `tests/Feature/Auth/RoleTest.php` asserts the role id as the integer it now is (see `Resources` 2.0.0).

## 2.3.0 — 2026-09-16

### Added

- Sign-in by a one-time code sent to the phone (`auth/phone/code`, `auth/phone/verify`): six digits, only the hash stored, ten minutes to live, a new code supersedes the open ones and the fifth wrong guess burns it. Delivery goes through the `LoginCodeSender` contract; the bootstrap ships the `log` driver, which refuses in production. `LOGIN_CODE_EXPOSE` returns the code as `dev_code` locally. Covered by `tests/Feature/Auth/LoginCodeTest.php` (from largatudoevai).

## 2.2.0 — 2026-09-16

### Added

- `PATCH auth/me`: a person fixes their own name without holding `users.update`, the permission that also grants editing everybody else. Covered by `tests/Feature/Auth/MeTest.php`, whose guard assertion was confirmed by opening the door to `role_id` and watching it go red (from largatudoevai).

## 2.1.1 — 2026-09-15

### Fixed

- Login with a deactivated account and the **correct** password answers `auth.accountInactive`
  (speak to an administrator) instead of `auth.login.invalidCredentials`. Wrong password on that
  account still matches an unknown username — revealing inactive without a password would
  enumerate accounts. Covered by `AuthTest`.

## 2.1.0 — 2026-09-14

### Added

- `design` permission group with `design.read` — the Brand, Components, Features and Showcase screens. It replaces a front-end check on the administrator's role slug, which could not be granted from the permissions tab (from lifeshifter).

### Fixed

- **`RoleResource` returns every declared group**, granted or not. It dropped groups a role had no row for, which made a new permission unreachable from the Roles screen — `design.read` included (from lifeshifter).
- **Editing a role is a delta.** `PATCH /roles/{id}/permissions` upserts only the keys sent; `PATCH /roles/{id}` upserts the named groups' keys and removes only the keys of declared groups that were not named. Both used to delete every row and reinsert (from lifeshifter).
- **An unknown group id is a 422** on create and on update, instead of being skipped — which read exactly like "remove this group" (from lifeshifter; the create side is ours).
- **An undeclared permission key is a 422** on `PATCH /roles/{id}/permissions`. The request validated each value as a boolean and never the key, so anyone with `permissions.update` could store a key no group declares, granted ahead of the route that would one day read it (found by this round's security review).
- **A user permission row means a disagreement with the role.** Create and update store only the keys that differ from the role's answer; the full matrix the form sends had frozen a copy of the role per user (from lifeshifter). `php artisan permissions:prune-redundant [--dry-run]` removes the copies already stored.
- **An account cannot delete itself** (422, `users.cannotDeleteSelf`), as it already could not deactivate itself (from lifeshifter).
- `GET auth/permission-groups` requires `permissions.read`, `users.create` or `users.update` — the same audience as the roles read — instead of any session (from lifeshifter, adapted: the user form reads it too).
- `AuthTest`: an unknown `api/*` route answers a 404 in JSON even when the caller asked for HTML (from lifeshifter's `ApiErrorShapeTest`; the 401 half was already covered here).
- Two findings PHPStan had been holding back, surfaced once these files entered a diff: `RoleResource` declares `@mixin Role` (six undefined-property reads), and `RoleRepository::updateRole()` returns a real `bool` instead of the affected-row count.
- `RoleResource` marks each group `isHeld` — the role has a row for one of its keys. Returning every group made the profile form pre-select all of them, and a group removed there came back on the next open (found by this round's independent review).

## 2.0.0 — 2026-09-14

### Breaking
- `GET auth/users` answers a paginated payload (`data.data`, `data.total`, …) and accepts `page`, `per_page` and `profile_type`.
- `auth/roles` routes require a permission per verb: reading takes `permissions.read`, `users.create` or `users.update`; every write takes `permissions.update`. Reading was public and writing needed only a session.

### Added
- `EnsureAccountActive` signs a deactivated account out on its next request (401 `auth.accountInactive`).
- An administrator cannot deactivate their own account, by toggle or by edit (422 `users.form.cannotDeactivateSelf`).
- Registration verifies the CPF check digits and normalises the phone (`App\Helpers\Brazil`).

### Fixed
- An account parked for approval (`registration.new_user_status` = inactive) was signed in on verification and could use the whole app.

## 1.2.0 — 2026-06-01

### Added
- `RoleTest.php`: feature tests for role CRUD + permission sync endpoints.
- Unit tests for `AuthService`, `UserService`, `PermissionService`, `ApiResponse`.

## 1.1.0 — 2026-05-30

### Added
- Pest feature tests for auth, user endpoints; RefreshDatabase enabled.

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
