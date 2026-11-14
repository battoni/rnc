---
version: 2.2.0
origin: pendulum
based-on: 2.1.0
---

# RULES — User Module

> For AI agents. Last updated: 2026-09-17.

## Domain

User entity management and session state. This module owns the `User` type, the session user store, and all user CRUD operations (create, read, update, archive, delete).

## What belongs here

- `User` entity: fields, status, permissions array, role assignment
- Session state: who is currently logged in, their permissions
- User CRUD services and views
- Username availability check

## What does NOT belong here

- Auth flows (login, OTP, password reset) → `Auth` module
- Role/permission definitions → `Auth` module
- App-wide UI state → `src/stores/ui.store.ts`

## Public Barrel (`@UserModule`)

Exports: enums (`USER_STATUS`), interfaces (`User`, and the payloads `CreateUserPayload`, `UpdateUserPayload`, `GetUsersParams`), services, `useUserStore`, types, views (route configs).

## `User` Interface

```typescript
interface User {
  id: number;
  name: string;
  last_name: string;
  username: string;
  role: string; // display name
  role_slug?: string; // machine-readable slug (e.g. 'administrator')
  status?: Status | string;
  permissions?: string[];
  profile_picture?: string;
  email?: string | null;
  email_verified?: boolean; // proven address — a boolean, never the timestamp
  phone?: string | null;
  profile_type?: string | null;
}
```

`role_slug` is used for role-based guards (e.g. `ROLES.SUPERADMIN` check in `TheNavbar`). `role` is display-only.

## Store — `useUserStore`

The single source of truth for the authenticated session.

| Action                                     | Purpose                                                                            |
| ------------------------------------------ | ---------------------------------------------------------------------------------- |
| `setUserAndPermissions(user, permissions)` | Set user after login                                                               |
| `clearUser()`                              | Clear on logout                                                                    |
| `fetchMe()`                                | Re-fetch session user from API (called by auth guard and after permission changes) |
| `hasPermission(key)`                       | Check if session user has a specific permission string                             |

`permissions` is stored separately from `user.permissions` because permission updates (after role save) must be reflected immediately without re-fetching the full user object.

## Services

| Service                | Purpose                                                                                           |
| ---------------------- | ------------------------------------------------------------------------------------------------- |
| `GetMeService`         | Fetch the authenticated session user                                                              |
| `GetUsersService`      | One PAGE of users (`PaginateResult<User>`): `page`, `per_page`, `ids`, `order_by`, `profile_type` |
| `GetUserService`       | Get a single user                                                                                 |
| `CreateUserService`    | Create a user                                                                                     |
| `UpdateUserService`    | Update a user                                                                                     |
| `ArchiveUserService`   | Toggle active/inactive status                                                                     |
| `DeleteUserService`    | Delete a user (only allowed when inactive)                                                        |
| `CheckUsernameService` | Check if a username is available (supports `excludeId` for edit mode)                             |

## Cross-Module Dependencies

- **`Auth` module** imports `useUserStore` to clear session on logout and check session state in the auth guard.
- This module imports `GetPermissionGroupsService` and `GetRolesService` from `@AuthModule` inside `MAddEditUserForm` — roles and permission groups are Auth domain data.

## Modules per firm (rnc-4)

- **`hasModule(key)` is the firm's opt-in list** (rnc-4 F3c, R17, `FIRM_MODULE` in `@Enums`): `firm` and `platform` arrive with `auth/me` and the sign-in answers only, so `setUserAndPermissions` keeps them when a later payload (a profile save) has no `firm` key, and `clearUser` drops them. A firm-less account (the platform operator) has no opt-in module — its requests run as the platform firm, which has none.

## Do Not

- **Do not manage role definitions here** — roles are Auth domain; this module only assigns a role name to a user.
- **Do not call `GetMeService` directly in components** — use `userStore.fetchMe()` instead.
