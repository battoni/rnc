# Review Design Conventions

> Reviews app.rnc changes for design system violations. Run from the rnc monorepo root.

**This command runs in plan mode. Do not edit any file until the plan is approved.**

---

## Step 1 — Ask scope

Ask the user:

> Which scope should I review?
> 1. **Uncommitted changes only** — `git diff HEAD` (staged + unstaged)
> 2. **Against `development`** — all commits on this branch not yet in `development`
> 3. **Against `main`** — all commits on this branch not yet in `main`

Wait for the answer before continuing.

## Step 2 — Read the design rules

Read both rule files so you know exactly what to check against:

- `.claude/rules/celer-06-design-system.md`
- `.claude/rules/celer-01-vue-checklist.md` (Colors and Design System sections)

Both are self-contained. If the project keeps `codelumen/`, `codelumen/design-system.md` has the
same spec with longer examples — optional reading, never a source a finding depends on.

## Step 3 — Get the diff

Run the appropriate command based on the answer in Step 1:

- **Uncommitted:** `git diff HEAD -- app.rnc/` and `git diff --cached -- app.rnc/`
- **Against development:** `git diff development...HEAD -- app.rnc/`
- **Against main:** `git diff main...HEAD -- app.rnc/`

For each changed `.vue` file in the diff, read the full file — not just the diff lines. Design violations often require surrounding context to spot.

## Step 4 — Find violations

Check every added or modified line in every changed `.vue` file against these categories:

### Colors
- Any `lime-*`, `gray-*`, `black-*` Tailwind class
- Any built-in Tailwind palette: `sky`, `emerald`, `amber`, `red`, `green`, `neutral`, `yellow`, `slate`
- Any hardcoded hex value, `bg-white`, or `text-black`
- Raw brand-ramp shades in components (`bg-surface-50`, `text-ink-700`, `border-surface-200`, …) — these don't flip for dark; require role tokens (`bg-panel`, `text-heading`, `border-line`, …)
- State colors (`bg-red-*`, `text-red-*` etc.) that should use `danger-*`, `warn-*`, `success-*`, `info-*`

### Typography
- Form labels not using `text-sm text-muted mb-2 block`
- Label error state not using `text-danger-700`
- Heading/title text not using `text-heading`
- Muted text not using `text-muted`
- Readable text (a hint, timestamp, subtitle, placeholder) in `text-subtle` — `subtle` is for icons and marks only

### Spacing
- Field wrappers not using `flex flex-col gap-2`
- Form containers not using `gap-4` for vertical rhythm
- Ad-hoc `mt-*` / `mb-*` on individual fields instead of gap on parent

### Radius
- Arbitrary radius values: `rounded-[Npx]`, `rounded-2xl`, `rounded-3xl`
- `rounded-full` on non-pill elements

### Buttons
- `<Button>` with custom `bg-*`, `border-*`, or `text-*` color classes for semantic intent that a `severity` prop already covers

### Dialogs
- Raw `<Dialog>` usage instead of `<MMainDialog>`

## Step 5 — Present the plan

Output violations grouped by file. Be specific — name the rule, the line, and the exact fix:

```markdown
### app.rnc/src/modules/Auth/components/molecules/MSignUpUser/MSignUpUser.vue

- Line 86: [typography — label] `text-gray-700 font-medium` → `text-muted` (no font-medium on standard labels)
- Line 102: [colors] `bg-red-100 text-red-700` → `bg-danger-100 text-danger-700`
- Line 140: [radius] `rounded-[6px]` → `rounded-md`

### app.rnc/src/modules/User/components/molecules/MAddEditUserForm/MAddEditUserForm.vue

- Line 22: [buttons] `<Button class="bg-danger-500 ...">` → `<Button severity="danger" ...>`
```

If there are no violations, state **✅ No design violations** and stop.

**Do not make any changes yet.** Wait for approval.

## Step 6 — Execute (after approval)

Apply every fix listed. Work file by file. Do not introduce changes beyond what is listed in the plan.
