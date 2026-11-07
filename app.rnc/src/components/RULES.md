---
version: 1.2.1
origin: pendulum
based-on: 1.2.1
---

# RULES — src/components/

> For AI agents. Last updated: 2026-09-17.

## Purpose

Shared, reusable UI components available across all modules. Organized by atomic design level.

## Folder Map

| Folder       | Prefix    | Use for                                                                                |
| ------------ | --------- | -------------------------------------------------------------------------------------- |
| `atoms/`     | `A`       | Single-responsibility primitives with no children (e.g. `AFormError`, `ALogo`)         |
| `molecules/` | `M`       | Compositions of atoms or PrimeVue components with a focused responsibility             |
| `organisms/` | `O`       | Complex compositions of molecules; may manage their own local state                    |
| `unique/`    | `The`     | Singletons rendered once per app (navbar, page header). No prefix rule — always `The*` |
| `templates/` | _(empty)_ | Page-level slot-driven skeletons with no business logic; currently unused              |

## When to add here vs. in a module

**Add to `src/components/`** when the component is used by 2+ modules OR is logically app-wide (navigation, layout chrome, shared UI primitives).

**Add to `src/modules/ModuleName/components/`** when the component is specific to one module's domain. Do not pre-emptively promote to shared.

## Naming

- `A*` — atom
- `M*` — molecule
- `O*` — organism
- `The*` — unique singleton

Each component lives in its own folder with `ComponentName/ComponentName.vue` + `index.ts` barrel.

## A country's components — `molecules/brazil/`

`MAddressFields` and `MLocalityFields` live one level down, in `molecules/brazil/`, beside the
other Brazil-only files (`services/brazil/`, `composables/brazil/`, `helpers/brazil/`,
`constants/brazil.constant.ts`, `interfaces/brazil.interfaces.ts`). A product outside Brazil
deletes the directory — and then searches for `<MAddressFields` / `<MLocalityFields`, because an
auto-imported component that no longer exists does not fail the build: Vue warns at runtime and
renders nothing. The TypeScript half (services, composables, interfaces) does fail the build.

The nesting costs nothing: `unplugin-vue-components` scans `src/components/*` recursively and
names a component by its file, not its path, so `<MAddressFields>` resolves as any other molecule.

## Scans over every template

Two specs at this folder's root read every `.vue` in the app — `components/`, `layouts/`,
`modules/`, `views/` and `App.vue` — because the rules they guard are ones ESLint does not see.
They live here because they started as scans of the shared units; a module screen broke the same
rule the week they were written (`BrandView`), so they read everything now:

- `unifiedClassBinding.spec.ts` — no element carries both `class` and `:class` (celer-04).
- `motionTokens.spec.ts` — no literal `duration-*` class; durations are motion tokens, so reduced
  motion shortens them (`styles/tailwind.css`).

`organisms/rowActions.spec.ts` covers the row actions every list organism draws: named, 44px under a
finger, 12px apart.

## Do Not

- **Do not import from sub-paths** — all public components are auto-imported by `unplugin-vue-components`; explicit imports are never needed in templates.
- **Do not put business logic** (calls to our API, store reads, routing) in atoms or molecules — keep those in organisms, unique components, or views.
  - **Exception: a public utility lookup.** A molecule may call a third-party public-data service
    from `@Services` (ViaCEP, IBGE) — directly or through a composable such as `useCepLookup` —
    when the lookup is the reason the molecule exists. `MAddressFields` and `MLocalityFields` do.
    Such a service carries no session, never throws and answers `null`/`[]` on failure, so the
    molecule stays usable when it fails.
  - _Retired wording:_ "no API calls" without qualification. It was written with our own API in
    mind; chapaja had to carry the CEP auto-fill as an "accepted deviation" in this file and in
    both molecules' notes. A block whose whole point is the lookup cannot hand it to each parent,
    so the exception is now the rule's own text.
- **Do not create a shared component for a single use case** — wait until it's needed by a second consumer.
