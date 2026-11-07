---
version: 1.1.1
origin: pendulum
based-on: 1.1.1
---
# RULES — App Models (User)

> For AI agents. App-wide models. Module-local models (`Role`, etc.) live under the module.

## Why `User` is here, not in Auth

`User` is referenced across the whole app (auth, ownership, relations), so it lives in
`app/Models/`. Module-specific models (`Role`, `RolePermission`, `UserPermission`) live in
`app/Modules/Auth/Models/`. Don't move `User` into the module.

## Intentional Decisions

- **`effectivePermissions(): array`** is the single source of truth for what a user can do:
  `role permissions ∪ direct user permissions`, deduped via
  `array_values(array_unique(array_merge(...)))`. Resources and `PermissionService` call
  this — do not reimplement the merge elsewhere.
- **Casts**: `password => 'hashed'` (never hash manually before save — the cast does it),
  `status => UserStatus::class` (enum), `email_verified_at => 'datetime'`.
- **`$hidden = ['password', 'remember_token']`** — defense-in-depth alongside the Resource.
- **Relations**: `role()` belongsTo, `permissions()` hasMany `UserPermission`. The
  `effectivePermissions` merge reads `role?->permissions` and `permissions` — both must be
  loadable.
- Uses `HasApiTokens` (Sanctum), `HasFactory`, `Notifiable`.

## Do Not

- Do not hash passwords manually — the `hashed` cast handles it.
- Do not duplicate the permission-merge logic; call `effectivePermissions()`.
