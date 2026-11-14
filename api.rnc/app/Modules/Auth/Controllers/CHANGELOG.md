# Changelog — Auth Controllers

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.5.0 — 2026-11-14

### Changed

- `AuthController::login` ends the session's live view-as as `logout` before the sign-in (rnc-4 verify P2-A4).

## 2.4.0 — 2026-11-14

### Changed

- `AuthController::me` answers `UserResource::withPlatform()` — `platform.name` beside the person (rnc-4 F2a, R9).

## 2.3.0 — 2026-09-17

### Changed

- `UserController::update` refuses a password without `users.reset_password` (403, `users.form.passwordResetPermissionRequired`) (review ARC-01).
- `UserController::checkUsername` takes `CheckUsernameRequest` + `CheckUsernameDTO`; a missing username is now a 422 instead of a 400, and a taken one answers 409 with `users.form.usernameTaken` (review ARC-14).
- `RoleController::update` / `updatePermissions` answer 404 for a role that does not exist, instead of a foreign-key 500 (review ARC-07).
- `RoleController::destroy` delegates both refusals to `RoleService::delete`; the messages are now i18n keys, the statuses (403 / 409 + `users`) are unchanged (review ARC-18).

## 2.2.1 — 2026-09-16

### Changed

- `RULES.md`: `DTO::from($request)` is correct as written — `config/data.php` makes it map `validated()`. The rule used to demand `->validated()` at every call site while most controllers passed the request (from chapaja-116).

## 2.2.0 — 2026-09-16

### Added

- `LoginCodeController` — `requestCode` and `verify` for the phone-code sign-in.

## 2.1.0 — 2026-09-16

### Added

- `MeController` — `PATCH auth/me`. Self-service for the fields an account owns about itself; everything somebody else decides stays on `auth/users/{id}` behind `users.update` (from largatudoevai).

## 2.0.1 — 2026-09-15

### Changed

- `UserController::index` takes `ListUsersRequest` — `profile_type` / `order_by` are validated
  instead of being read raw off the query string.

## 2.0.0 — 2026-09-14

### Breaking
- `UserController::index` answers `ApiResponse::paginated` and reads `profile_type` and `per_page`.

### Changed
- `update` / `archive` pass the acting user's id so the service can refuse self-deactivation.
- `RegisterController` does not sign in an account parked for approval.

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
