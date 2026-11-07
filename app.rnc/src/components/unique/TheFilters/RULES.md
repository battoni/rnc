---
version: 1.0.1
origin: pendulum
based-on: 1.0.1
---

# RULES — TheFilters

> For AI agents. Last updated: 2026-09-17.

## Purpose

Structural wrapper for the filter bar area in authenticated views. Provides a consistent flex layout for search inputs, sort controls, and any other filtering controls.

## Usage

Place `<TheFilters>` as the **first child of the default slot** in `TheLayout`, before any content grids or lists.

```vue
<TheLayout>
  <template #pageHeader>...</template>

  <TheFilters>
    <MSearchMultiselect ... />
    <MOrderBy ... />
  </TheFilters>

  <!-- content grid, table, etc. -->
</TheLayout>
```

## Inline layout

`TheFilters` uses `flex flex-wrap items-center gap-3`. All children render inline on the same row. If a child expands to full width (e.g. an `InputGroup` without a width constraint), it will wrap onto a new row and break the inline appearance.

**Every direct child of `<TheFilters>` must be naturally sized or explicitly constrained:**

- Search `InputGroup`: add `class="sm:w-fit"` so it doesn't stretch.
- `MOrderBy`: already uses `w-fit` on its inner `InputGroup` — no extra class needed.
- Any other control: ensure it does not default to `width: 100%`.

## Do Not

- **Do not use `TheFilters` inside `#pageHeader`** — it belongs in the default slot.
- **Do not add page content** (cards, tables, lists) inside `TheFilters` — it is filters only.
- **Do not override the flex layout** with wrapper divs — put controls directly as children of `<TheFilters>`.
- **Do not place a full-width child** inside `TheFilters` — it will wrap to its own row.
