# Design System

This page is the long form of app.rnc's UI styling decisions — the reasoning and the examples. The rule agents apply, `.claude/rules/celer-06-design-system.md`, is self-contained (a project may delete `codelumen/`), so when the two disagree the rule and the code win and this page is the one to fix.

It documents the design rules implemented in:

- `app.rnc/src/styles/theme/colors.css`
- `app.rnc/src/libraries/primevue/theme.ts`
- `app.rnc/src/styles/libraries/primevue.css`

Use this page before adding new styles or tokens.

## 1) Foundations

### 1.1 Font families

- **Primary UI font**: `Space Grotesk`
- **Monospace/supporting text font**: `Sometype Mono`

Usage:

- Headings, labels, buttons, menu text: `Space Grotesk`
- Paragraph-like body copy and code-like content: `Sometype Mono`

These are `battoni-dev`'s faces, and mono prose is that theme's own choice. The typefaces come from
the active theme's `--font-sans` / `--font-mono`; the preset and the reset name no family, and a
theme that does not declare mono prose gets sans body copy.

### 1.2 Radius scale

Source of truth: `--radius-*` in the active theme. The values are `battoni-dev`'s; the ROLE of each
step is what holds across themes.

- `none`: `0`
- `xs`: `3px` — chips, tags, swatches
- `sm`: `5px` — small controls, inline pills
- `md`: `8px` — form fields, buttons
- `lg`: `12px` — cards, panels, lists
- `xl`: `16px` — modals, large full-bleed containers

Component defaults currently used:

- Form fields (`InputText`, `Select`, `Textarea`, etc.): `md`
- Buttons default: `md`
- Buttons with `rounded`: `lg` (not full pill)
- Card root: `lg` (`12px` in `battoni-dev`)
- Content/overlay (select/popover): `md` (`8px` in `battoni-dev`)

The preset's `var(--radius-*, …)` fallbacks (`6px` for `md`, `8px` for `lg`) only apply when a theme declares no radius; they are not the scale.

### 1.3 Focus ring

- Width: `1px`
- Style: `solid`
- Color: `primary.500`
- Offset: `0` for form fields, `2px` global default

---

## 2) Color system

app.rnc is **multi-theme** (one active brand theme chosen at build time) and **light/dark**
(toggled at runtime). Components consume **role tokens** and **state palettes** — never raw
brand ramps. See `app.rnc/src/styles/theme/RULES.md` for the full system.

### 2.1 Brand palette ramps (raw material)

Per-theme fixed scales; they do **not** flip by scheme:

- **Primary**: action and emphasis color (`--color-primary-*`, main `#c0e021`)
- **Ink**: dark text scale (`--color-ink-*`, main `#141413`)
- **Surface**: neutral scale (`--color-surface-*`, main `#888b8d`)

Don't use these shades directly in components — use the role tokens below.

### 2.2 Role tokens (structure — flip per scheme)

| Token / utility | Use for |
| --- | --- |
| `bg-canvas` | page background |
| `bg-panel` | cards, panels, raised surfaces |
| `bg-panel-muted` | hover / subtle fills |
| `text-heading` | titles, strong headings |
| `text-body` | default body text |
| `text-muted` | labels, secondary text, hints, placeholders |
| `text-subtle` | icons and marks — never text |
| `border-line`, `divide-line` | default borders / dividers |
| `border-line-strong` | emphasized borders |

These are CSS custom properties overridden under `[data-theme='dark']`, so the same utility
resolves to the dark value — no `dark:` variants needed.

### 2.3 Semantic state palettes (meaning — same in both schemes)

Use semantic scales for state-driven UI:

- `success` (`--color-success-*`)
- `info` (`--color-info-*`)
- `warn` (`--color-warn-*`)
- `danger` (`--color-danger-*`)
- `help` (`--color-help-*`)
- `contrast` (`--color-contrast-*`)

### 2.4 Text role mapping

- **Primary title/header text**: `text-heading`
- **Default body text**: `text-body`
- **Form labels / secondary labels**: `text-muted`
- **Muted/supporting text** (hints, timestamps, subtitles, placeholders): `text-muted`
- **Icons and decorative marks**: `text-subtle` — it never carries a sentence. `muted` clears 4.5:1
  and `subtle` 3:1 on canvas, panel and panel-muted, in both schemes
  (`app.rnc/src/styles/theme/RULES.md`). The old "`text-muted` to `text-subtle`" range is retired: it
  let hints pick the faint tier, which measured 1.72:1 on a panel.
- **Error text**: `text-danger-700`

---

## 3) Typography rules

### 3.1 Form labels

Standard field label style:

- `text-sm`
- `text-muted`
- `mb-2 block`

Error state:

- Keep same structure and switch color to `text-danger-700`.

### 3.2 Dialog/form titles

Dialog titles use `text-heading` (strong, scheme-aware); labels below keep the muted hierarchy using `text-muted`.

---

## 4) Component specs

## 4.1 Buttons

Default shape and behavior:

- Radius: `md` (`8px` in `battoni-dev`)
- Primary uses `primary.color` with `primary.contrast.color`
- Secondary uses `surface.100/200/300` for resting/hover/active

Rules:

- Do not add ad-hoc color classes for base semantic intent if tokenized severity is available.
- Prefer `severity="primary|secondary|success|info|warn|danger"` before custom utility overrides.

## 4.2 Inputs and fields

Field tokens:

- Horizontal padding: `0.75rem`
- Vertical padding: `0.5rem`
- Border radius: `md` (`8px` in `battoni-dev`)
- Border color baseline: `surface.300`

Applies to:

- `InputText`
- `Textarea`
- `Select`
- `MultiSelect`
- `Password`
- Other `form.field`-based PrimeVue controls

## 4.3 DataTable chrome

Global DataTable border rules:

- Header and cell border color: `surface.200`
- Header bottom border emphasis: `surface.300`

These are enforced in `primevue.css` to keep neutral table lines across views.

## 4.4 Cards

- Card radius: `lg`; `xl` is for a modal or a full-bleed container.
- A card is a raised surface: `bg-panel` + `shadow-lift`, no outline. A border that SEPARATES content
  (a divider, a grid rule, a data-carrying `border-l-4`) stays a border; a data mark never takes relief.
- The flat card (neutral background + `border-line-strong`, no shadow) is retired — the theme's relief
  vocabulary replaced it (`app.rnc/src/styles/theme/RULES.md`).

---

## 5) Spacing rules

Use tokenized spacing utilities and existing component spacing before introducing new values.

Common field block spacing pattern:

- Field wrapper: `flex flex-col gap-2`
- Label margin: `mb-2`
- Form vertical rhythm: `gap-4`

---

## 6) Implementation snippets

### 6.1 Standard label + input

```vue
<div class="flex flex-col gap-2">
  <label class="mb-2 block text-sm text-muted" for="firstName">First Name</label>
  <InputText id="firstName" name="firstName" placeholder="Enter first name" />
</div>
```

### 6.2 Label with validation

```vue
<label :class="['mb-2 block', fieldError ? 'text-danger-700' : 'text-muted']">
  {{ $t('form.fieldName') }}
</label>
```

---

## 7) Do / Don't

Do:

- Use semantic tokens and utility classes mapped to the token system.
- Keep label color consistent with `text-muted` unless error.
- Reuse existing radius scale (`xs`..`xl`) and component defaults.

Don't:

- Hardcode one-off hex colors in components.
- Re-introduce circular semantic token references such as `semantic.primary.500 -> {primary.500}`.
- Use ad-hoc per-screen overrides when a theme token can solve it globally.

---

## 8) Change management

When updating design rules:

1. Update tokens in `app.rnc/src/styles/theme/colors.css` and/or `app.rnc/src/libraries/primevue/theme.ts`.
2. Add any required global structural overrides in `app.rnc/src/styles/libraries/primevue.css`.
3. Update this page in `codelumen/design-system.md`.
4. Validate with `npm run lint` and `npm run type-check` in `app.rnc`.

