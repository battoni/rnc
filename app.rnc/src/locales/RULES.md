---
version: 1.13.0
origin: pendulum
based-on: 1.11.0
---

# Locale File Conventions

## Structure

Every locale file (`en.json`, `pt-BR.json`, etc.) follows a three-tier grouping at the **top level**:

1. **COMMON** — generic, cross-cutting translations (`common`, `errors`)
2. **COMPONENT** — translations owned by a specific shared component (`address`, `imageUpload`, `navigation`, `theme`)
3. **MODULE** — feature module and view translations (`auth`, `features`, `finance`, `ideas`, `news`, `notFound`, `personalization`, `rolesAndPermissions`, `showcase`, `tips`, `users`)

Groups appear in this order. Keys are sorted **alphabetically within each group**.

```
common      ← COMMON
errors      ← COMMON
address     ← COMPONENT (MAddressFields, MLocalityFields)
imageUpload ← COMPONENT (MImageUpload)
navigation  ← COMPONENT (TheNavbar)
theme       ← COMPONENT (AColorSchemeToggle)
auth        ← MODULE
features    ← MODULE
finance     ← MODULE
ideas       ← MODULE
news        ← MODULE
notFound    ← MODULE (views/NotFound)
personalization ← MODULE
rolesAndPermissions ← MODULE
showcase    ← MODULE
tips        ← MODULE
users       ← MODULE
```

## The firm in a string (rnc-4 R14, R15)

A project string (`local/*.json`) never names a tenant — Franco, Reinaldo, Willy, APW — and says "RNC"
only under a `channel` key (`src/test/noTenantNames.spec.ts`). A string that needs the HOST firm writes
`{firmShort}` (its short name, else its full name) or `{firmName}` (its full name), and no call site
passes it: `libraries/i18n` turns both into links to `firm.short` / `firm.name`, which `useBrandTheme`
fills from the firm's answer. `{firm}` stays what it was — a parameter the caller passes (the portal's
partner firm, the repricing letter's), which the host placeholders never touch.

When adding a new top-level key, also register it in `scripts/sort-locales.mjs` under `TOP_LEVEL_GROUPS`.
An unregistered key still sorts, as a module, and the script prints a `!` line naming it — seven
keys (`imageUpload` among them, a component's section filed among the modules) had piled up
unregistered before it said so.

---

## Key Order Within Every Object

Within any nested object, at every level:

1. **One-liners first** — keys whose value is a `string`, sorted **ASC**
2. **Multi-liners second** — keys whose value is a nested `object`, sorted **ASC**

```json
{
  "cancel": "Cancel",
  "save": "Save",
  "title": "My Title",
  "actions": { "delete": "Delete", "edit": "Edit" },
  "form": { "name": "Name" }
}
```

This applies **recursively** — every nested object follows the same rule.

---

## Automation

`npm run sort-locales` re-sorts all locale files according to these rules. Run it after any structural change (adding keys, renaming, moving).

**ESLint** (`npm run lint`) catches duplicate keys and invalid JSON numbers but does **not** enforce sort order — `jsonc/sort-keys` only knows alphabetical and cannot express the one-liners-first tier or the COMMON/COMPONENT/MODULE grouping.

---

## Adding a New Key

1. Add the key in the correct position manually, or just run `npm run sort-locales` after adding it anywhere.
2. Add the same key to **all** locale files.
3. If adding a new top-level section, register it in `scripts/sort-locales.mjs`.
