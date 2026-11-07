---
name: celer-advanced-patterns
description: "Advanced Vue 3 patterns for app.rnc — composable design, store architecture, performance optimization, complex form patterns. Load when building a new module, designing a composable, refactoring a store, working on complex form validation, or optimizing a large component."
paths:
  - "app.rnc/**"
---

# app.rnc — Advanced Vue 3 Patterns

> **Scope:** This skill applies to the **app.rnc/** sub-project (Vue 3 SPA, TypeScript).

## When to load this skill

Load when: building a new module, designing a composable, refactoring a store, working on complex form validation, or optimizing a large component.

## Composable design

- One composable per concern. Never bundle unrelated logic.
- Composables return refs and functions only — no components, no side effects in the body.
- Name: `useNoun` (e.g. `useAuth`, `usePagination`) — never `useDoSomething`.
- If a composable needs to be shared across modules, it belongs in `src/composables/`.
- If it is module-specific, it belongs in `src/modules/{Module}/composables/` (create folder if needed).
- **Anything timed reads the wall clock.** A countdown or a timeout stores its deadline and derives
  what is left from `Date.now()` on each tick and on `visibilitychange` — `useCountdown` does this for
  a countdown. Counting ticks is wrong on a phone, which suspends timers while the person is in
  another app. A spec fakes only `['Date', 'setInterval', 'clearInterval']` and moves the clock
  without running the interval.

## Store architecture (Pinia)

- One store per domain module (`useAuthStore`, `useUserStore`).
- State is flat — no nested reactive objects unless the nesting is the domain model itself.
- Actions are async-first. Never mutate state directly outside the store.
- Getters are pure computed values — no side effects.
- Stores are initialized once per app; do not create stores inside components that unmount.
- **An optimistic write keeps a snapshot and restores it.** Write locally first, into every loaded
  aggregate that shows the row; if the API refuses, put the previous objects back and rethrow so the
  view can say so. Replace state, never mutate it in place — that is what makes the snapshot a plain
  reference.
- **A list everything needs has one door.** `ensureXLoaded()` returns the in-flight promise when a
  load is already running, so the navigation and two views asking on mount make one request, not
  three. It is the only action callers outside the module use.

## Performance

- `v-memo` on large static lists where re-renders are expensive.
- `shallowRef` for large non-reactive data (tables, chart data, lookup maps).
- `defineAsyncComponent` for route-level components and heavy modals.
- Never import an entire icon library — import per icon from the specific path.
- Avoid watching deeply nested objects; flatten state or use explicit field watchers.

## Complex forms (Vee-Validate + PrimeVue Forms)

- Define the Yup schema outside the component — in `constants.ts` or a dedicated `schemas.ts`.
- Use `useForm` at the top of setup; destructure `handleSubmit`, `errors`, `isSubmitting`.
- Field-level validation with `useField` only when the field is reused across multiple forms.
- Never build a form with `v-model` directly on reactive state — use Vee-Validate's `defineField` or PrimeVue's Form component.
- Submission payloads are built from `values` returned by `handleSubmit` — never read from refs directly.

## Module structure (when creating a new module)

```
src/modules/ModuleName/
  components/        ← A/M/O prefix: atoms, molecules, organisms
  composables/       ← module-specific composables (optional)
  services/          ← one service per API call
  store/             ← one Pinia store (useModuleNameStore.ts)
  views/             ← route-level views
  enums.ts
  constants.ts
  interfaces.ts
  types.ts
  index.ts           ← barrel: re-exports everything public
```

- `index.ts` is the public API. Nothing outside the module imports a sub-path.
- Services go in `services/` — one file per API call, named `verbNoun.service.ts`.
- Views are never imported directly — they are registered in the router.
