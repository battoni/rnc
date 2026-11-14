# Changelog — Auth Repositories

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.4.0 — 2026-11-13

### Changed

- rnc-4 F1a (AM2): `findAllUsers`, `findAllActiveUsers` and `findSoleUserByPhone` take the current firm; new `findFirmUserById`; user reads eager-load `firm` (the users list ran one query per row once users had a firm, `AuthHardeningTest`).

## 2.3.0 — 2026-11-07

### Added

- `UserRepository::findUserByPartnerId`, `saveUser` (fill + save, so the `hashed` cast runs — `updateUser` is a query update) and `deleteUserSessions` (the `sessions` table rows of one user) for `LinkedAccountService` (rnc-2 F1).

## 2.2.0 — 2026-09-17

### Changed

- `UserRepository::findAllUsers` / `findUserById` eager-load `role`: the users index no longer runs one query per row (review ARC-08).
- `PendingRegistrationRepository::updatePendingRegistrationCode` resets `attempts`; `registerFailedPendingRegistrationAttempt` counts a wrong guess and expires the row at the ceiling (review ARC-04).

### Documented

- `syncRolePermissions` is described as it is (delete + bulk `insert`) and as create-only; the delta path (`upsertRolePermissions` + scoped `removeRolePermissions`) every edit takes is now in the rules (from lifeshifter-014).

## 2.1.0 — 2026-09-16

### Added

- `LoginCodeRepository`: the newest unconsumed code, locked for the verify transaction; superseding every open code for a phone; `wasLatestLoginCodeBurned`.
- `UserRepository::findSoleUserByPhone` — the account a phone signs in to, only when exactly one carries it.

## 2.0.1 — 2026-09-15

### Added

- `UserPermissionRepository::findUserPermissionRowsByUserId` / `deleteUserPermissionsByIds` and
  `UserRepository::cursorAllUsers` for `permissions:prune-redundant`.

## 2.0.0 — 2026-09-14

### Breaking
- `UserRepository::findAllUsers` returns a `LengthAwarePaginator` and filters by `profileType`.

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
