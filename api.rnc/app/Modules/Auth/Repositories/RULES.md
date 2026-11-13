---
version: 2.3.0
origin: pendulum
based-on: 2.2.0
---
# RULES — Auth Repositories

> For AI agents. Layer conventions beyond `arcus-api-architecture`.

## Role

The ONLY layer that touches Eloquent. All queries live here. Methods use **semantic,
entity-prefixed names** (`findAllUsers`, `findUserById`, `createUser`, `updateUser`,
`syncRolePermissions`) — never generic `find`/`create`.

## Intentional Decisions

- **Always re-hydrate the relation after a write**: `return $user->load('role');` after
  create/update. The Resource downstream expects the relation loaded — returning an
  unhydrated model would drop `role`/`permissions` from the response. Same for
  `RoleRepository` (`->load('permissions')`).
- **Eager-load on reads**: `with('role')` on `findAllUsers`/`findUserById`,
  `with('permissions')` on roles — prevents N+1 and feeds the Resource's `whenLoaded`.
- **Partial update via `array_filter(..., fn ($v) => $v !== null)`**: `updateUser` strips
  null DTO fields so a PATCH only updates provided keys. Note this means a field can never
  be *cleared* to null through update — intentional for these entities.
- **Backend owns the slug**: `createRole` derives `slug` with `Str::slug($dto->name)`. Slugs
  are never accepted from the client.
- **`syncRolePermissions` is delete-then-insert, and it serves only a role being CREATED**: it
  deletes every row of the role and bulk-`insert`s the matrix (no timestamps) — permissions are
  name-string rows, not a pivot sync.
- **Every edit of an existing role is a delta**: `upsertRolePermissions` (`updateOrCreate` per key)
  for what was sent, and `removeRolePermissions` for the keys of DECLARED groups the role no longer
  holds. A key that was not sent is not a denial, and a key of no declared group is never touched.
  Never route an edit through `syncRolePermissions`: a payload with three keys would wipe the rest
  and still answer 200 (lifeshifter).
- Find methods return `?Model`; the service/controller maps null to 404.

## Do Not

- Do not return a model from a write without `->load(...)` its relations.
- Do not accept or trust a client-supplied slug.
- Do not put business rules here — only data access.
