---
version: 1.3.0
origin: pendulum
based-on: 1.3.0
---
# RULES — Auth DTOs

> For AI agents. Layer conventions beyond `arcus-api-architecture`.

## Role

Spatie `Data` objects that carry validated input from controller to service/repository.
Built with `XxxDTO::from($request)`, which carries only what the FormRequest validated:
`config/data.php` enables `FormRequestNormalizer`, so a field without a rule never arrives.

## Intentional Decisions

- **camelCase properties map snake_case input**: the request validates `role_id`, the DTO
  exposes `public ?int $roleId`. Spatie Data handles the mapping. Keep PHP-side properties
  camelCase; keep validation rule keys matching the API's snake_case contract.
- **Store vs Update DTOs are separate** (`UserStoreDTO` / `UserUpdateDTO`): update DTOs make
  fields nullable so the repo's `array_filter` partial-update works. Do not merge them.
- DTOs are pure data — no methods, no defaults beyond constructor promotion, no validation
  (that's the FormRequest's job).

## Do Not

- Do not validate inside a DTO — validation belongs in the Request.
- Do not reuse a Store DTO for updates (nullability differs by design).
