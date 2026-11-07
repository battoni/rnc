---
description: Module file separation, enum naming, and service patterns for app.rnc (Vue 3 / TS)
paths:
  - "app.rnc/**/*.vue"
  - "app.rnc/**/*.ts"
---
# Module Structure (CRITICAL)

A module under `src/modules/{Module}/` has a fixed shape — directories for the layered pieces, flat files for the shared surface, and a barrel at the root:

```
{Module}/
  components/        ← dir (atoms / molecules / organisms / templates)
  composables/       ← dir of use*.ts
  services/          ← dir of *.service.ts (+ index.ts barrel)
  stores/            ← dir (only if the module owns Pinia state)
  views/             ← dir of *.view.vue (only if the module owns routes)
  enums.ts           ← flat file
  constants.ts       ← flat file
  interfaces.ts      ← flat file (the module's public type surface)
  types.ts           ← flat file
  index.ts           ← module barrel
  routes.ts          ← only if the module owns routes
```

## The barrel (`index.ts`) — the module's public API (CRITICAL)

The barrel re-exports everything the module exposes. It is the **only** entry point other modules import from (`@AuthModule`, never `@AuthModule/services` — see `02-vue-imports`).

```typescript
// index.ts
export * from './components';
export * from './composables';
export * from './services';
export * from './enums';
export * from './constants';
export type * from './interfaces';
export type * from './types';
```

If a symbol is missing from `@Module`, add it to the barrel (or to `interfaces.ts` for shared types) — never deep-import the defining file.

# File Separation (CRITICAL)

Do **not** mix concerns in the same file.

- **enums.ts** – TypeScript enums (fixed sets of values, provide type + runtime)
- **constants.ts** – Static lists, const objects, literal values (e.g. option lists, config maps)
- **types.ts** – Types only: type aliases, type re-exports from enums
- **interfaces.ts** – Interfaces and payload types. **If an interface will be used outside the file that defines it (another service, a component, another module), it must go in interfaces.ts** – not in the service or component file.

# Resource vs payload interfaces (CRITICAL)

Two different contracts, two different interfaces, and they never cross:

- **The resource** mirrors what the API RETURNS — the `JsonResource` keys, as they arrive
  (`last_name`, `email_verified`, `held_cents`).
- **The payload** mirrors what the `FormRequest` VALIDATES — its keys exactly, whatever their case.

Do not reuse a resource interface as a payload (or the reverse) to save a declaration: the day the
two shapes drift, the type still compiles and the request sends a field the API ignores.

# Enums vs Constants

**Enums** go in `enums.ts`. Use when you need a fixed set of string/number values.

**Constants** go in `constants.ts`. Use for static data: option arrays, lookup maps, config objects.

# Enum Naming (CRITICAL)

Enums use **UPPER_SNAKE_CASE** to differentiate from interfaces (PascalCase).

❌ **NEVER:** PascalCase enums (looks like interface)

```typescript
enum FormFieldType { ... }
```

✅ **ALWAYS:** UPPER_SNAKE_CASE for enums

```typescript
enum FORM_FIELD_TYPE { ... }
enum FORM_OPTION_SOURCE_TYPE { ... }
```

# Service Pattern (CRITICAL)

Services use `export default async function` with a named function:

```typescript
// nameOfService.service.ts
export default async function nameOfService(
  id: number,
  payload: Payload
) {
  return someProvider(id, payload);
}
```

The barrel file re-exports with PascalCase:

```typescript
// services/index.ts
export { default as NameOfService } from './nameOfService.service';
```

# Single Export Block (CRITICAL)

Use one export block at the end. No inline `export const` or `export type`.

❌ **NEVER:**

```typescript
export const FOO = 'foo';
export type Bar = string;
```

✅ **ALWAYS:**

```typescript
const FOO = 'foo';
type Bar = string;

export { FOO };
export type { Bar };
```

# Reaching another module's screen (CRITICAL)

A module links to a screen another module owns by its **route name**, exported as a constant from the
owner's barrel — never by importing the owner's view, store or component to get there.

```typescript
// modules/Orders/constants.ts
const ORDER_ROUTE_NAMES = { detail: 'order-detail' } as const;

// modules/Catalog/views/Products/ProductsView.view.vue
router.push({ name: ORDER_ROUTE_NAMES.detail, params: { id } });
```

A name is the whole contract a link needs. An import makes the linking module load the other one and
creates the dependency cycle that the barrel-only rule exists to prevent.
