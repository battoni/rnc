---
version: 2.8.0
origin: pendulum
based-on: 2.5.0
---
# RULES — Auth Services

> For AI agents. Layer conventions beyond `arcus-api-architecture`.

## Role

Business logic + orchestration. Sits between controllers and repositories. Receives DTOs,
returns models (or throws). Holds DI'd repositories via `private readonly`.

## Intentional Decisions

- **CRUD services may be thin pass-throughs** to the repository (see `UserService` —
  `createUser` just calls `userRepository->createUser`). This is correct *when there is no
  business rule yet*. Add logic here as it appears; do NOT push it into the repository.
- **Real logic lives here, not in the repo.** Example: `AuthService::authenticate` does the
  `Hash::check` and throws `ValidationException` on bad credentials — credential
  verification is a service concern, the repo only *fetches* by username.
- **Throw, don't return error envelopes.** Services throw (e.g. `ValidationException`);
  controllers/handlers turn those into responses. Services never build `ApiResponse`.
- **A failure that must persist is returned from the transaction, then thrown.**
  `LoginCodeService::verify` counts a wrong guess inside `DB::transaction`; throwing there would
  roll the count back, so `spend` returns the exception and `verify` throws it after the commit.
- Return `?Model` for find/update (null = not found, controller maps to 404); `bool` for
  delete success.

## Do Not

- Do not query Eloquent directly in a service — that is the repository's job.
- Do not build HTTP responses or reference `ApiResponse` here.
