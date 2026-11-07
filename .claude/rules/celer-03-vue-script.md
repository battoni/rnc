---
description: Script structure, reactivity order, and code patterns for app.rnc (Vue 3 / TS)
paths:
  - "app.rnc/**/*.vue"
  - "app.rnc/**/*.ts"
---
# Vue API

- Use Vue 3.5+ with Composition API
- Always use `<script setup lang="ts">`

# Code Structure Order (CRITICAL)

After imports, code MUST follow this order:

1. Directives – `vMyDirectiveName`
2. Macros – defineEmits, defineProps (ASC)
3. Stores
4. Helpers
5. Composables – **separate section**, do NOT mix with reactivity
6. Reactivity (see below)
7. provide()
8. Watchers
9. Lifecycle
10. Methods – grouped with `// HELPERS` and `// EVENTS`

# Stores and Composables Order (CRITICAL)

**Stores** (`use*Store()`) always come before **composables** (`use*()` non-store). Separate the two groups with a blank line. Within each group: one-line declarations first ASC, then multi-line ASC — same pattern as computed.

❌ **NEVER:** mix stores and composables without separation

```typescript
const { setUserAndPermissions } = useUserStore();
const { push } = useRouter();
const { t } = useI18n();
const toast = useToast();
```

✅ **ALWAYS:** stores first (ASC), blank line, composables (ASC)

```typescript
const { setUserAndPermissions } = useUserStore();

const toast = useToast();
const { push } = useRouter();
const { t } = useI18n();
```

# Reactivity Order (CRITICAL)

Composables (e.g. `useI18n()`, `useToast()`) go in section 5, **before** reactivity. Do not place them inside the reactivity block.

Within reactivity: follow the numbered list below. **For each subgroup, apply the same pattern: one-line declarations first, then multi-line, each tier sorted ASC.**

1. Non-reactive variables (e.g. `let counter`, `const FIELD_OPTIONS`) – one-line first ASC, then multi-line ASC
2. Special refs: defineModel(), useTemplateRefs(), yupResolver(), etc. – one-line first ASC, then multi-line ASC
3. ref – one-line first ASC, then multi-line ASC
4. readonly – same
5. reactive – same
6. **computed (CRITICAL): two tiers — do not merge.**
   1. **One-line computeds** – callback body is a **single-line** arrow (e.g. `computed(() => !!props.user)` or `computed(() => t('key'))`). Sort **ASC alphabetically by variable name**. **No blank lines between consecutive one-line computeds** (one continuous block). Put **one blank line** after the last one-line computed **before** the first multi-line computed.
   2. **Multi-line computeds** – callback body spans **multiple lines** (object/array literals, `.map(...)`, `yup.object()...`, etc.). Sort **ASC alphabetically by variable name**.

❌ **NEVER:** place a multi-line computed **before** a one-line computed only because the name sorts earlier (e.g. `groupedPermissionOptions` before `isEditMode`).

✅ **ALWAYS:** all **one-line** computeds (ASC, with **dependencies first**: if `header` uses `isEditMode`, `isEditMode` comes before `header` even if that breaks pure ASCII order), then all **multi-line** computeds (ASC, same dependency rule).

# Watchers and Lifecycle (CRITICAL)

Must be one-liners calling event functions.

❌ **NEVER:**

```typescript
watch(count, (newVal) => { console.log(newVal); });
onMounted(() => { console.log('bad'); });
```

✅ **ALWAYS:**

```typescript
watch(count, onCountUpdate);
onMounted(onComponentMount);

function onCountUpdate(newVal: number) { /* ... */ }
function onComponentMount() { /* ... */ }
```

# Methods (CRITICAL)

- Use **function declarations**, NOT arrow functions
- Group with `// HELPERS` and `// EVENTS`

**// HELPERS** – Pure functions, getters, formatters (e.g. `getFieldLabel`, `buildPayload`)
**// EVENTS** – Handlers, callbacks, and the functions that call API services (e.g. `onClick`, `onSubmit`, `fetchUsers`)

❌ **NEVER:** `const handleSubmit = (p) => { ... };`
✅ **ALWAYS:** `function handleSubmit(p) { ... }`

# Semantic Variable Names – NO ABBREVIATIONS (CRITICAL)

**No single-letter or abbreviated variables. Ever.**

❌ **NEVER:**
```typescript
forms.find((f) => f.slug === slug);
items.map((i) => i.id);
arr.sort((a, b) => a.order - b.order);
idx, ft, u, p
```

✅ **ALWAYS:**
```typescript
forms.find((form) => form.slug === slug);
items.map((item) => item.id);
arr.sort((first, second) => first.sortOrder - second.sortOrder);
index, fieldType, user, payload
```

# Constant Value Casing (CRITICAL)

Use **`UPPER_SNAKE_CASE`** for non-reactive **constant values** — payload objects, config objects, default/seed objects, static option lists, toast configs. These are fixed data, not state (same casing as enums in `05-module-conventions`).

Use **`camelCase`** for **reactive** declarations (`ref`, `computed`, `reactive`), function parameters, and local working variables. A `const` bound to a `ref()` / `computed()` / `reactive()` is **reactive state**, not a constant value — it stays `camelCase`.

❌ **NEVER:** camelCase for a fixed constant value

```typescript
const payload = { name: form.name, fields: form.fields };
const defaultForm = { name: '', fields: [] };
```

✅ **ALWAYS:**

```typescript
const PAYLOAD = { name: form.name, fields: form.fields };
const DEFAULT_FORM = { name: '', fields: [] };

const isEditMode = computed(() => !!props.user); // reactive → camelCase
```

# No Else (CRITICAL)

Use early returns or separate `if`s. In promise callbacks, early return, never else.

❌ **NEVER:** `if (data) resolve(slug); else trySlug(next);`
✅ **ALWAYS:** `if (data) { resolve(slug); return; } trySlug(next);`

**This rule bans `else` branches and "wrap the whole body" `if` — not the ternary operator.** After guard clauses, a **single terminal `return`** may stay a **ternary** when it is short and readable.

# Early Returns, Not Wrappers (CRITICAL)

❌ **NEVER:** `if (data && form) { form.fields = [...]; }`
✅ **ALWAYS:** `if (!data || !form) return; form.fields = [...];`

# Extract Complex Conditions (CRITICAL)

When `if` has 2+ operands, extract to semantic const.

❌ **NEVER:** `if (isEditMode.value && currentForm.value) { ... }`
✅ **ALWAYS:** `const shouldUpdate = isEditMode.value && currentForm.value; if (shouldUpdate) { ... }`

# Blank Lines Between Instructions (CRITICAL)

Add blank line between distinct instructions (emit, toast, if blocks).

# API Batching and Payload (CRITICAL)

- Do NOT loop and call API N times. Send single payload.
- **Payload is created in the caller (parent).** The service receives `(id, payload)` and uses it.

❌ **NEVER:**
```typescript
for (const field of draftFields) await AddFormFieldService(id, field);
```

✅ **ALWAYS:**
```typescript
const PAYLOAD = { name: form.name, fields: form.fields };
UpdateFormService(id, PAYLOAD).then(...);
```

# Remove Unused Code (CRITICAL)

Delete unused variables, computed, imports, functions.

# No Empty Catch (CRITICAL)

❌ **NEVER:** `.catch(() => {});`
✅ **ALWAYS:** `.catch(() => { toast.add({ severity: 'error', ... }); });`

# One-liner Catch Syntax (CRITICAL)

❌ **NEVER:** `.catch(() => { x.value = null; });`
✅ **ALWAYS:** `.catch(() => (x.value = null));`

# Backend Owns (CRITICAL)

Do not generate or enforce on the frontend: **Slugs**, **Uniqueness**.

# Event Naming (CRITICAL)

Events: `on{Action}` (e.g. `onSuccess`, `onError`).

# Async / Promises (CRITICAL)

Use chained `.then` / `.catch` / `.finally`. No try/catch.

# Data Updates – Granular (CRITICAL)

Use API response to update local ref (add, replace, remove). No full refetch when entity is returned.

# Dates and Time (CRITICAL)

**A calendar day or a local date-time never goes through `toISOString()`.** It answers in UTC: in
São Paulo it is already tomorrow from 21:00, and a typed day (local midnight) is already yesterday
anywhere east of UTC. Nothing fails — a wrong date is still a valid date. Serialize with
`toLocalDateString` / `toLocalDateTimeString` (`@Helpers`), and read a masked `dd/mm/yyyy` with
`fromDayMonthYear`. A real instant from the API (ISO with an offset) is the only thing `new Date()`
may parse.

❌ **NEVER:** `date.toISOString().slice(0, 10)`
✅ **ALWAYS:** `toLocalDateString(date)`

**A countdown is measured by the wall clock** (`useCountdown`, `@Composables`): store the deadline and
derive the seconds left from `Date.now()` on each tick and on `visibilitychange`. A phone suspends
timers while the person is in another app, and a countdown that decremented once per tick came back
still locked long after the minute it promised.

# Section Comments

Only `// HELPERS` and `// EVENTS`. No `// 1. Vue`, `// Methods`, etc.
