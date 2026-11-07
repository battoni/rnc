---
version: 1.0.1
origin: pendulum
based-on: 1.0.1
---

# RULES — MLocalityFields

> For AI agents. Last updated: 2026-09-17.

## Purpose

The field block for a place at city level: an optional CEP, city and UF, and — when the parent
asks — a free-text region inside the city. A complete CEP fills city and UF from ViaCEP. It is the
front half of the API's `localityColumns()` block (`api.rnc/app/Helpers/RULES.md`).

## The contract

|                  |                                                                           |
| ---------------- | ------------------------------------------------------------------------- |
| `v-model`        | `LocalityFields` (`@Interfaces`), required — one object, mutated in place |
| `showRegion`     | `boolean`, default `false` — renders the optional region input            |
| Owns             | the fields, the CEP lookup and its two status lines                       |
| Does **not** own | validation, the submit, the payload, the coordinates                      |

## Intentional Decisions

- **Everything `MAddressFields/RULES.md` says about the object model, the lookup, `useId()`,
  `inputId` and the status lines holds here too.** Only the differences are below.
- **The CEP is optional.** A place at city level is often known without one; the label says so.
- **ViaCEP fills city and UF only.** Street and district mean nothing at this level, and the
  region is the person's own word — ViaCEP never writes it.
- **The region is generic free text** ("Zona Leste", "Grande ABC") and hidden by default. The source
  used it for the product's own areas; that list stays in a product. A form that needs a fixed set
  of regions owns its own control instead of this input.
- **The city is an `InputText`, not an IBGE-fed `Select`.** `GetCitiesByStateService` exists and
  a product may build a picker on it, but a free-text city is what this block promises, and
  swapping it changes what the parent receives.

## Do Not

- **Do not add validation or a submit** — "is the city required here" is the parent's question.
- **Do not let ViaCEP write `region`.**
- **Do not replace the city input with an IBGE picker in place** — make it a separate molecule or
  a prop, so existing parents keep their free-text city.
