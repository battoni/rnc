# Changelog — Auth Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.1.2 — 2026-09-17

### Removed

- `data/permission-groups.json`. Nothing imported it: the groups arrive from the API (`auth/permission-groups` and the role resource), and the file had drifted to two groups against seven in `PermissionGroupsHelper.php` (rnc-105). Its `JUNCTION_PATTERNS` entry in `cortex/sync/sync.py` is retired with its reason, and `test_sync.py` now checks the path is NOT a junction.
- `views/RolesAndPermissions/interfaces.ts`, the re-export kept for `MPermissionPanel` (CEL-12).

### Fixed

- `MLoginCode` (1.3.2): the resend-wait toast is `warn`, not `error` — a wait is a refusal on a rule (celer-07).

## 2.1.1 — 2026-09-17

### Changed

- `Permission` and `Profile` are defined in the module's `interfaces.ts`, beside `PermissionGroup` — not in `views/RolesAndPermissions/interfaces.ts`, which five services reached into by path. The view's file only re-exports them until `MPermissionPanel` stops importing it. The barrel exports the same names.
- The role, login, logout and support-names services name their default function after their file (`export default async function getRoles(`), per the service pattern. The barrel is unchanged.

## 2.1.0 — 2026-09-16

### Added

- `SIGN_UP_CODE_RESEND_COOLDOWN_SECONDS` (60) — the wait on the sign-up verification screen's resend (chapaja-207).

## 2.0.0 — 2026-09-16

### Changed

- **Breaking:** `Profile.id` is a `number` and the role services (`GetRoleService`, `UpdateRoleService`, `UpdateRolePermissionsService`, `DeleteRoleService`) take a numeric id — the API sends role ids as integers now (from lifeshifter-269). Permission-group ids stay strings: they are keys (`users`), not rows.

## 1.3.0 — 2026-09-16

### Added

- Sign-in by a code sent to the phone, against the real API: `RequestPhoneCodeService` (`auth/phone/code`) and `VerifyPhoneCodeService` (`auth/phone/verify`), `MLoginPhoneCode`, `LOGIN_CODE_ERROR` and the new `constants.ts` (`LOGIN_CODE_LENGTH`, `LOGIN_CODE_RESEND_COOLDOWN_SECONDS`). From largatudoevai.

### Removed

- `LookupPhoneService` and its `LookupPhone*` interfaces. Asking "does this phone have an account" is the question the API refuses to answer; the phone step now asks for a code and hears the same reply either way.

## 1.2.0 — 2026-09-16

### Changed

- Branches on `HTTP_STATUS` instead of a bare status number. The same code appears in eight files, and a reader had to rebuild what the API means by it every time (from largatudoevai).

## 1.1.1 — 2026-09-15

### Fixed

- `useLoginError` documents and covers `auth.accountInactive` (deactivated account + correct
  password) the same way as other 422 keys — toast and field copy. Wrong credentials stay
  `auth.login.invalidCredentials`.

## Unreleased

### Added

- Self-service registration against the API: `RegisterUserService`, `RequestEmailOtpService`
  and the new `VerifyEmailService` are real calls now, not mocks.
- `MSignUpUser` asks for an e-mail. It could not before, and a flow that verifies an address
  has to have one to verify.
- `VerifyEmailView` — where the code is typed, and what an account parked for approval is told.
- `PermissionGroup.isHeld`, sent with a role.

## 1.1.0 — 2026-09-09

### Added

- `composables/useLoginError.ts` (+ spec) — the shared refusal handling for every login form, and `LoginApiError` in `interfaces.ts`.

### Changed

- `MLoginForm`, `MLoginUsernamePassword` and `MLoginPasswordCredentials` call `onLoginError` instead of each carrying a copy of the same 35-line `.catch`.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
