---
title: Template structure, attribute order, and formatting rules for app.rnc (Vue 3)
outline: deep
---

::: info Generated page
This page is generated from `.claude/rules/celer-04-vue-template.md` — **edit the source rule, not this page.** Activates for: `app.rnc/**/*.vue`.
:::

# i18n in Templates — `$t()`, never `t()` (CRITICAL)

> **Auto-enforced by ESLint** (`vue/no-restricted-syntax` bans `t(` in `<template>`). `vue/no-restricted-syntax` only inspects the template, so script-side `t()` is untouched.

Templates use the **globally-injected `$t()`**. The script-scoped `t()` (from `useI18n()`) is for the **`<script>` block only** — yup resolver messages, toast summaries/details, computed labels.

Only `import { useI18n }` + `const { t } = useI18n()` when the **script itself** calls `t()`. If translation is used **only** in the template, do not import `useI18n` at all — use `$t()` directly.

❌ **NEVER** (in `<template>`):

```vue
<label>{{ t('auth.flow.login.email') }}</label>
<Button :label="t('auth.flow.continue')" />
```

✅ **ALWAYS:**

```vue
<label>{{ $t('auth.flow.login.email') }}</label>
<Button :label="$t('auth.flow.continue')" />
```

# Parent-Children Spacing (CRITICAL)

When a parent has 2+ children, add a blank line between each child. Applies recursively.

❌ **NEVER:**

```vue
<FloatLabel>
  <InputText />
  <label>...</label>
</FloatLabel>
```

✅ **ALWAYS:**

```vue
<FloatLabel>
  <InputText />

  <label>...</label>
</FloatLabel>
```

# Tags

- Explicit closing tags (even for `<li>`, `<p>`)
- Self-closing must end with `/>`

## A routed view has exactly ONE root node (CRITICAL)

A view rendered by the router is the child of `App.vue`'s `<Transition mode="out-in">`. Two root
nodes — two elements, or an element with a **comment beside it** — make the component
fragment-rooted, and the Transition cannot drive it: the leave never finishes, so the next view
never mounts and the app goes blank until a reload. Nothing is logged.

```vue
<!-- ❌ NEVER: the comment is a second root node -->
<template>
  <!-- what this screen is for -->
  <TheCenteredLayout>…</TheCenteredLayout>
</template>

<!-- ✅ ALWAYS: one root, the comment inside it -->
<template>
  <TheCenteredLayout>
    <!-- what this screen is for -->
    …
  </TheCenteredLayout>
</template>
```

A back link that used to sit beside the layout goes inside it; absolute positioning is unaffected.
`src/test/routedViewSingleRoot.spec.ts` fails on any view with more than one root.

## Multiline element structure (CRITICAL)

When an element's opening tag spans multiple lines, the closing `>` must be on its own line at the element's indentation level. Content goes on the next indented line. The closing tag goes on its own line.

❌ **NEVER:**

```vue
<label
  class="mb-2 block text-sm text-gray-500"
  for="email"
  >{{ t('auth.flow.login.email') }}</label
>
```

✅ **ALWAYS:**

```vue
<label
  class="mb-2 block text-sm text-gray-500"
  for="email"
>
  {{ t('auth.flow.login.email') }}
</label>
```

# Tailwind Class Order (CRITICAL)

Tailwind classes are sorted automatically by `prettier-plugin-tailwindcss`. **Never manually order or reorder them.** Run Prettier and let the plugin determine the correct order.

# Unified `class` / `:class` binding (CRITICAL)

When an element has BOTH a static `class` AND a bound `:class`, **unify them into a single `:class` array**. The static string becomes the first array entry; dynamic parts (objects, conditional strings, etc.) follow.

- Only static classes → keep `class`.
- Only bound classes → keep `:class`.
- Both → unify into a single `:class` array (never two attributes on the same element).

❌ **NEVER:**

```vue
<a
  class="flex w-full items-center hover:bg-gray-50"
  :class="{ 'bg-gray-50': isExactActive }"
>
```

```vue
<span
  class="rounded-sm p-2 text-xl"
  :class="[icon, { 'bg-lime-200': isExactActive }]"
/>
```

✅ **ALWAYS:**

```vue
<a
  :class="[
    'flex w-full items-center hover:bg-gray-50',
    { 'bg-gray-50': isExactActive },
  ]"
>
```

```vue
<span :class="['rounded-sm p-2 text-xl', icon, { 'bg-lime-200': isExactActive }]" />
```

# v-for and :key Order (CRITICAL)

`v-for` must appear first. `:key` is UNIQUE — it sorts alphabetically with static attrs (group 6). Since 'k' > 'c', `:key` comes after `class`.

❌ **NEVER:**

```vue
<div :key="field.id" v-for="field in fields" class="...">
<div v-for="ft in TYPES" :key="ft.type">
```

✅ **ALWAYS:**

```vue
<div
  v-for="field in fields"
  class="rounded border p-4"
  :key="field.id"
>
<div v-for="{ id, label, type } in fieldTypes" class="..." :key="id">
```

# Attribute Order (CRITICAL)

> **Auto-enforced by ESLint on save** (`vue/attributes-order` with `alphabetical: true`, `vue/v-bind-style` with `sameNameShorthand: 'always'`).

**Order of groups:**

1. **v-for**
2. **Conditionals** – `v-if`, `v-else-if`, `v-else`, `v-show`
3. **Other directives** – `v-model`, `v-slot`, etc.
4. **Simple without assignment** – `binary`, `fluid`, `plain`, `showButtons`, `showClear`, `text`, ASC
5. **GLOBAL** – `id` only
6. **Static + UNIQUE** – `autocomplete`, `class`, `icon`, `key`/`:key` (UNIQUE), `mask`, `name`, `severity`, `size`, `type`, static `pt:*` — all ASC alphabetically
7. **Bound** – Any `:` / `v-bind:` prop **except `:key`**: `:class`, `:modelValue`, etc. — all ASC alphabetically
8. **Events** – `@click`, `@submit`, ASC

**Static `pt:*` before `:class` (CRITICAL).** `pt:segment="static string"` is group 6 (static), `:class` is group 7.

❌ **NEVER:**

```vue
<Card
  :class="cardClassList"
  pt:body="pb-0"
>
```

✅ **ALWAYS:**

```vue
<Card
  pt:body="pb-0"
  :class="cardClassList"
>
```

# Which prop reaches the real `<input>` (CRITICAL)

A PrimeVue form control is often a wrapper around the `<input>`, and an attribute given to the
wrong one renders without complaint — on the wrong element. A `<label for>` then points at nothing,
and a password manager never sees the field.

| Component | The id a `<label for>` needs | Native input attributes (`autocomplete`, …) |
| --- | --- | --- |
| `InputText`, `Textarea`, `InputMask` | `id` — the component IS the input; `inputId` is not a prop and falls through as a stray attribute | directly |
| `Password` | `inputId` | `:inputProps="{ autocomplete: '…' }"` — a bare `autocomplete` lands on the wrapper `<div>` |
| `ToggleSwitch`, `Checkbox`, `RadioButton`, `Select`, `MultiSelect`, `DatePicker`, `InputNumber`, `AutoComplete` | `inputId` — an `id` lands on the root `<div>`, which a click on the label cannot toggle | — |

`src/test/primevueInputProps.spec.ts` fails on the two shapes that shipped in seven forms at once: a
`Password` with a bare `autocomplete`, and an `InputText` or `Textarea` with `inputId`.

# Per-Component Examples

## Button – with label/loading

```vue
<Button
  class="mt-1"
  icon="pi pi-plus"
  size="small"
  :label="$t('forms.optionList.addItem')"
  :loading="saving"
  @click="onAdd"
/>
```

## Form (PrimeVue)

```vue
<Form
  id="field-config-form"
  class="flex flex-col gap-4"
  :initialValues
  :resolver
  @submit="onSubmit"
>
```
