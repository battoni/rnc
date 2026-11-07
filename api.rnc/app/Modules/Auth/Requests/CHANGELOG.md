# Changelog — Auth Requests

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.5.1 — 2026-09-21

### Changed

- `UpdateRolePermissionsRequest` skips the grant cap when the role is the administrator: granting
  to it gives the actor nothing they do not already hold, and capping it made a lost key
  unrecoverable.

## 1.5.0 — 2026-09-20

### Added

- `GrantableByActor` on every `permissions` payload and `AssignableRole` on `role_id`: a request
  hands out only keys the person making it already holds, and only assigns a role whose keys they
  hold (ARC-03). `ResolvesActorPermissions` reads them from `PermissionService`, so role keys and
  personal overrides are merged in one place. Revoking is untouched.

## 1.4.0 — 2026-09-17

### Added

- `CheckUsernameRequest` (review ARC-14).

### Changed

- `StoreUserRequest` / `UpdateUserRequest` refuse undeclared permission keys, and `UpdateRolePermissionsRequest` uses the shared rule with an i18n key instead of `__('validation.in')` (review ARC-02, ARC-13).
- `StoreRoleRequest` / `UpdateRoleRequest` answer a taken name with `rolesAndPermissions.form.nameTaken` (review ARC-13).

## 1.3.0 — 2026-09-16

### Added

- `RequestLoginCodeRequest` and `VerifyLoginCodeRequest`, both normalising the phone in `prepareForValidation()` so the rules, the row and the lookup see the same digits.

## 1.2.0 — 2026-09-16

### Added

- `UpdateMeRequest` — name and last name, and that is the whole list. The e-mail is not here: it carries `email_verified_at`, so changing it is a flow (send a code, confirm it), never a field a PATCH moves quietly (from largatudoevai, narrowed).

## 1.1.1 — 2026-09-15

### Added

- `ListUsersRequest` — validates `ids`, `order_by`, `profile_type` (`Rule::in` registration
  profile keys), `per_page` and `page` for `GET auth/users`.

## 1.1.0 — 2026-09-14

### Changed
- `RegisterRequest` validates the CPF check digits (`Brazil\Cpf`) and normalises the phone (`Brazil\PhoneNumber`).

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
