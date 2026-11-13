---
version: 1.2.0
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
- **`partner_id` and `partner()`** (rnc-2 R1): a partner's portal login points at its Partner.
  `Partner` is firm-scoped, so the relation resolves only inside the current firm — a login
  pointing at another firm's partner finds none (`ResolveCurrentPartner`, plan AM1). No foreign key:
  `partners` is created later and belongs to the Partner module (rnc-1 W25). Unique (one login row
  per partner, reopened on regrant); staff rows hold null.

## Do Not

- Do not hash passwords manually — the `hashed` cast handles it.
- Do not duplicate the permission-merge logic; call `effectivePermissions()`.
