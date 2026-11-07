---
version: 2.1.2
origin: pendulum
based-on: 2.1.2
---

# RULES — Auth Module

> For AI agents. Last updated: 2026-09-17.

## Domain

Authentication flows, session bootstrap, roles, and permissions. This module owns everything related to who the user is and what they are allowed to do.

## What belongs here

- Login, sign-up, OTP, password reset flows
- Role CRUD and permission assignment
- Session entry point (logout, `GetMeService` is in User — see below)

## What does NOT belong here

- User entity management (CRUD, status, profile picture) → `User` module
- App-wide UI state → `src/stores/ui.store.ts`

## Public Barrel (`@AuthModule`)

Exports: components, composables (`useLoginError`), constants (`LOGIN_CODE_LENGTH`, `LOGIN_CODE_RESEND_COOLDOWN_SECONDS`, `SIGN_UP_CODE_RESEND_COOLDOWN_SECONDS`), enums (`LOGIN_CODE_ERROR`, `ROLES`), interfaces (all service payloads + `LoginApiError`, `PermissionGroup`, `Profile`, `Permission`), services, types, views (route configs), `AuthRoutes`.

`Permission`, `PermissionGroup` and `Profile` are defined in the module's `interfaces.ts`. Services import them from there (`../interfaces`); components and code outside the module import them from `@AuthModule` as `import type` (erased at build, so it loads no barrel), as `MAddEditProfileForm` and `MPermissionPanel` do.

**Retired:** "`Permission` and `Profile` are re-exported from `views/RolesAndPermissions/interfaces.ts`". Types shared by five services and a molecule do not belong to one view, and the services reached them by path. That file was deleted on 2026-09-17, once `MPermissionPanel` imported the type from `@AuthModule`.

## Enums

| Enum               | Values                                                                                    |
| ------------------ | ----------------------------------------------------------------------------------------- |
| `LOGIN_CODE_ERROR` | `DELIVERY_FAILED`, `EXPIRED`, `INVALID` — the stable `code` of a refused phone sign-in    |
| `ROLES`            | `SUPERADMIN = 'administrator'`, `ADMIN = 'admin'`, `SUPPORT = 'support'`, `USER = 'user'` |

## Services

| Service                           | Purpose                                  |
| --------------------------------- | ---------------------------------------- |
| `LoginRequestService`             | Username + password login                |
| `LoginPasswordCredentialsService` | Phone/email + password (multi-mode)      |
| `RequestEmailOtpService`          | Re-send the registration e-mail code     |
| `RequestPhoneCodeService`         | Ask for a sign-in code for a phone       |
| `VerifyPhoneCodeService`          | Spend the phone code (opens the session) |
| `VerifyLoginCodeService`          | Mock — e-mail code sign-in, no API yet   |
| `LogoutService`                   | End session                              |
| `RegisterUserService`             | Sign up a new user                       |
| `RequestPasswordResetService`     | Request reset code                       |
| `ResendPasswordResetCodeService`  | Resend reset code                        |
| `ConfirmPasswordResetService`     | Submit new password + code               |
| `GetRolesService`                 | List all roles                           |
| `GetRoleService`                  | Get a single role                        |
| `CreateRoleService`               | Create a role                            |
| `UpdateRoleService`               | Update role name/description/groups      |
| `UpdateRolePermissionsService`    | Update permission flags for a role       |
| `DeleteRoleService`               | Delete a role                            |
| `GetPermissionGroupsService`      | List all permission groups               |
| `GetSupportNamesService`          | Get support admin names for recovery UI  |

## Routes

`AuthRoutes` is a `RouteRecordRaw[]` composed from: `ForgotPasswordRoutes`, `LoginRoutes`, `SignUpRoutes`, `TermsRoutes`, `RolesAndPermissionsRoutes`. Register via `AuthRoutes` in the router — do not import individual route arrays.

## Cross-Module Dependencies

- **Imports `useUserStore`** from `@UserModule` in `attachAuthGuard` and `LogoutService` to clear session state on logout.
- **Does not own the `User` entity** — session user data lives in `useUserStore`.

## A refused sign-in is handled in ONE place (CRITICAL)

There are three doors into `auth/login` — `MLoginForm` (username), `MLoginUsernamePassword` and
`MLoginPasswordCredentials` (phone/e-mail) — and which one a product shows is an env decision,
not a behavioural one. They are three ways of naming the same person, so a refusal must read the
same through all three.

**`useLoginError` owns that, and it owns `apiError` with it.** A form calls
`.catch(onLoginError)` and binds `apiError`; it does not inspect `error.response.status` itself.

This is not tidiness. The three forms each carried a copy of the same 35-line `.catch`, and when
the 429 branch was added it had to be pasted into all three by hand — a fourth entry point would
have shipped without it and put Laravel's raw "Too Many Attempts." on a Portuguese screen. A new
case goes in the composable and reaches every door at once.

**The split it encodes:** a refusal the form can restate — a 422 carrying an i18n key, a 429 —
sets `apiError` AND toasts. Anything else toasts only, because "something went wrong" pinned
under a password box tells the reader nothing they can act on.

**A 422 for wrong password and unknown account is the same key on purpose.** Do not invent a
client-side split between those two. A deactivated account with the right password is a different
key (`auth.accountInactive`) — translate whatever the API sent; do not guess inactive locally.

## Do Not

- **Do not add user management features** (CRUD, archive, status) here — those belong in `User` module.
- **Do not import `Permission` or `Profile` by sub-path** — they are re-exported from the barrel.
- **Do not write a `.catch` on a login call.** Use `useLoginError`; a form that handles its own statuses drifts from the other two the first time a case is added.
