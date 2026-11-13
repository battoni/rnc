---
version: 2.1.0
origin: pendulum
based-on: 2.0.0
---
# RULES — Auth Resources

> For AI agents. Layer conventions beyond `arcus-api-architecture`.

## Role

`JsonResource` classes shape the API output. Every model returned to the client goes through
a Resource — controllers never return raw models.

## Intentional Decisions

- **Relations are conditional, never assumed loaded**: `role` uses `whenLoaded('role')`;
  `permissions` uses `$this->when(relationLoaded(...), fn () => $this->effectivePermissions())`.
  This is why repositories must `->load(...)` after writes — an unhydrated model silently
  omits these keys.
- **`permissions` is computed, not a column**: it calls `User::effectivePermissions()`
  (role ∪ user perms, deduped). It only appears when role or permissions are loaded.
- Shape is explicit and hand-listed (`id`, `name`, `username`, `email`, `status`, `role`,
  `permissions`) — no `parent::toArray()`. `password`/`remember_token` are also `$hidden` on
  the model as defense-in-depth.

## Do Not

- Do not access a relation without `whenLoaded`/`relationLoaded` guarding it.
- Do not expose `password` or tokens.
- Do not change a field's key/shape without a MAJOR version bump (breaks API consumers).
