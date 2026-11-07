---
version: 1.5.1
origin: pendulum
based-on: 1.5.1
---
# RULES — Auth Requests

> For AI agents. Layer conventions beyond `arcus-api-architecture`.

## Role

FormRequests own **validation only**. They run before the controller; the controller then
trusts `$request->validated()`.

## Intentional Decisions

- **`authorize(): return true`** — authorization is NOT done in the request here. Access
  control is handled elsewhere (middleware / permission checks). Do not add policy logic to
  `authorize()` unless that decision changes project-wide.
- **Uniqueness lives in the rules**: `unique:users,username`, `unique:users,email`. On
  update requests these need the ignore-self form (`Rule::unique(...)->ignore($id)`) — check
  the update request when adding fields.
- **Store vs Update requests are separate** and mirror the Store/Update DTO split.
- Validation rule keys use the API's **snake_case** (`role_id`), matching the JSON contract,
  not the DTO's camelCase.

## Do Not

- Do not perform authorization in `authorize()` (returns `true` by design here).
- Do not move uniqueness/format validation into the service or DB layer.
