# Changelog — Auth Services

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.7.0 — 2026-11-13

### Changed

- rnc-4 F1a (R4, AM2): `AuthService` and `LoginCodeService` refuse an account of another firm like a wrong password / wrong code (`FirmResolver::belongsTo`); `UserService` reads, edits, archives and deletes only inside the current firm and stamps it on create.

## 2.6.0 — 2026-11-07

### Added

- `LinkedAccountService` (rnc-2 F1, R1–R2): the accounts another module provisions for its own records — a partner's portal login. `openAccount` creates or reopens the row with a generated password returned once, `resetPassword` and `closeAccount` delete the user's stored sessions so a live cookie is a 401 next (plan AM5). Partner never writes `users` itself.

## 2.5.0 — 2026-09-21

### Added

- `UserService::refuseIfOutranked` on update, archive and delete — the actor must hold every key the
  target holds (`CANNOT_MANAGE_HIGHER_ACCOUNT`).

## 2.4.0 — 2026-09-21

### Changed

- `RoleService::updatePermissions` keeps the administrator whole: a revoke on that role throws a
  422 (`ADMINISTRATOR_KEEPS_EVERY_KEY`), and any save writes every declared key as granted.

## 2.3.2 — 2026-09-21

### Fixed

- The "a waiting address keeps what it holds" guard in `RegistrationService::register` applies in
  both verification modes. It only ran with verification on, so a row still waiting when a product
  switched verification off was overwritten by the next attempt and promoted at once. Found by an
  independent audit of ARC-05.

## 2.3.1 — 2026-09-20

### Fixed

- `RegistrationService::register` re-issues the code for an address already waiting instead of
  rewriting the pending row with the second attempt's data (ARC-05, account takeover).

## 2.3.0 — 2026-09-17

### Changed

- `UserService::create` / `update`, `RoleService::create` / `update` / `updatePermissions` and `RegistrationService` (verification off) write in a transaction (review ARC-11).
- `RoleService::update` / `updatePermissions` return null for a role that does not exist, checked before any write (review ARC-07).
- `RoleService` answers a taken name with `rolesAndPermissions.form.nameTaken` (review ARC-13) and owns the delete rules (`ADMINISTRATOR_SLUG`, role in use) (review ARC-18).
- `EmailVerificationService::verify` counts wrong guesses and burns the code on the fifth (`MAX_ATTEMPTS`, `auth.flow.signUp.verify.codeLocked`); the count is returned from the transaction so the rollback does not erase it (review ARC-04).

## 2.2.0 — 2026-09-16

### Added

- `LoginCodeService`. A phone with no account gets the same answer and no code. A wrong guess is counted by returning the failure from the transaction and throwing after it — thrown inside, the count rolled back with everything else. Superseding the open codes and writing the new one share a transaction, so a failed write leaves the old code usable. The guess that burns a code answers `code_locked`, and so does every attempt after it; a phone with no account gets a stored row too (nothing sent), so it walks the same `code_expired` / `code_locked` answers as a real one.

## 2.1.0 — 2026-09-16

### Added

- `MeService` — deliberately not `UserService::update` with your own id: that method also writes role, status and the permission matrix, and reaching it from an unguarded route would make "change my name" and "change my role" the same endpoint (from largatudoevai).

## 2.0.1 — 2026-09-15

### Fixed

- `AuthService::authenticate`: a deactivated account with the correct password answers
  `auth.accountInactive`; unknown username and wrong password still share
  `auth.login.invalidCredentials`.

## 2.0.0 — 2026-09-14

### Breaking
- `UserService::list` returns a `LengthAwarePaginator` and takes `profileType` and `perPage`.

### Added
- `update` / `archive` refuse to deactivate the acting user's own account.

## 1.1.0 — 2026-06-01

### Added
- Unit tests: `AuthServiceTest`, `UserServiceTest`, `PermissionServiceTest` with Mockery.

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
