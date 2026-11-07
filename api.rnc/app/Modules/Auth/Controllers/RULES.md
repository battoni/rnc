---
version: 2.3.0
origin: pendulum
based-on: 2.3.0
---
# RULES — Auth Controllers

> For AI agents. Layer conventions beyond `arcus-api-architecture`.

## Shape (every action)

Controllers are **thin**: build DTO from validated input, call one service method, wrap the
result in a Resource + `ApiResponse`. No business logic, no queries.

```php
public function store(StoreUserRequest $request): JsonResponse
{
    $dto = UserStoreDTO::from($request);
    $user = $this->userService->createUser($dto);

    return ApiResponse::created(data: new UserResource($user));
}
```

## Intentional Decisions

- **`DTO::from($request)` maps the *validated* input** — `config/data.php` enables
  `FormRequestNormalizer`, so a FormRequest is normalized with `validated()`, never `all()`.
  `DTO::from($request->validated())` is equivalent and fine where it already exists. (This rule used
  to demand `->validated()` at every call site; the config made the guarantee structural instead.)
- **Conditional returns use the ternary** with `ApiResponse::notFound()` for null results:
  `return $user ? ApiResponse::success(...) : ApiResponse::notFound(...);` — this is the
  house style for find-or-404, not an `abort()` or exception.
- **`ApiResponse::created()` for store**, `success()` for index/show/update, and a
  message-only `success(message: ...)` for destroy.
- **Constructor DI** of the service: `private readonly XxxService $service`.

## Do Not

- Do not touch repositories or models directly from a controller — go through the service.
- Do not return raw models or arrays — always a Resource inside `ApiResponse`.
