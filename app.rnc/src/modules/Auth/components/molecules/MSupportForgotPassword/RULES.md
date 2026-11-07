---
version: 1.0.1
origin: pendulum
based-on: 1.0.1
---

# RULES — MSupportForgotPassword

> For AI agents. Last updated: 2026-05-22.

## Purpose

Displays a list of support admin names (fetched from the API, with a constant fallback) to contact for account recovery.

## Intentional Decisions

- **`SUPPORT_NAMES` constant is the fallback**: if `GetSupportNamesService` fails or returns an empty array, the constant from `@Constants` is used. Do not remove the fallback.
- **Fetches `onBeforeMount`**: data loads before the component is visible to avoid a layout jump.
- **Shows 3 skeleton items during loading**: matches the typical number of support names.
- **No error toast on fetch failure**: the component silently falls back to the constant. This is intentional — the page still shows useful information even if the API is down.

## Dependencies & Context

- **`SUPPORT_NAMES` from `@Constants`**: the fallback list of names shown when the API is unavailable.
- **`GetSupportNamesService`**: fetches the current support team names from the backend.

## Do Not

- **Do not add an error state** — the fallback constant is the error state.
