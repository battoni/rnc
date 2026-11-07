---
version: 1.0.1
origin: pendulum
based-on: 1.0.1
---

# RULES — Shared services (`@Services`)

> For AI agents. Last updated: 2026-09-16.

## Purpose

Services that belong to no module: calls to public third-party APIs that several modules need.
A call to OUR API belongs to the module that owns the resource.

## Intentional Decisions

- **`brazil/` holds Brazil's public data** — `GetAddressByCepService` (ViaCEP) and
  `GetCitiesByStateService` (IBGE). They run client-side with `fetch`, not through `API`: they are
  not our API, carry no session, and must not feed the app-busy counter or the auth handling.
- **They never throw.** An incomplete input, an unknown value or a failed request answers `null` /
  `[]`, so a form falls back to manual entry instead of breaking.
- **The boundary keeps the third party's names** (`logradouro`, `localidade`, `uf`). Mapping them
  to the app's words is the consumer's job.
- **Removable as a directory.** A product outside Brazil deletes `brazil/` with its constants,
  interfaces, helpers, `composables/brazil/` and `components/molecules/brazil/`, which are its
  only importers.
- **Consumers.** `GetAddressByCepService` is called by `useCepLookup` (`@Composables`), which
  `MAddressFields` and `MLocalityFields` use. `GetCitiesByStateService` has no consumer yet — the
  city fields are free text on purpose (`MLocalityFields/RULES.md`).

## Do Not

- Do not route these through `API` / `@Providers`.
- Do not put a module's own endpoint here.
