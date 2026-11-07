---
version: 2.0.2
origin: pendulum
based-on: 2.0.2
# Without this the catalogue files ATip under `base` — it lives outside `modules/`. The atom
# and the Tip module are one feature: neither is any use alone.
feature: Tip
---

# RULES — ATip

> For AI agents.

## Purpose

Inline contextual help: an info icon that opens a dismissable popover with a tip. Only one tip popover may be open app-wide at a time.

The TEXT is not passed in — it comes from the database, keyed by an anchor. `ATip` takes the
anchor and resolves it through the tip store, so a manager can rewrite help without a deploy.

## Intentional Decisions

- **Renders nothing when the anchor has no tip.** Not an empty popover, not a disabled icon: an
  icon that opens onto nothing is worse than no icon. This is also what makes archiving a tip work
  — the affordance disappears with the text.
- **Content is HTML, rendered with `v-html`.** Safe ONLY because the API sanitises on write
  (`TipContentSanitizer`): what is stored is already the allow-listed subset. It is rendered
  through `withExternalLinks` (`@Helpers`), which puts back `target="_blank"` with its `rel` — the
  sanitiser strips both. Never feed `ATip`
  content from anywhere other than `GET /tips/active`.
- **Loads lazily, through the store.** `ensureLoaded()` on mount; the store de-duplicates, so a
  screen with six tips still makes one request.
- **Icon trigger**: a PrimeVue `Button` with `pi pi-info-circle`, styled `text rounded severity="secondary"` — PrimeVue tokens handle light/dark, hover, and active states. Only layout classes (`size-8 shrink-0 pointer-coarse:size-11`) are custom: drawn at 32px, the box itself grows to 44px on a coarse pointer. Not `touch-target`, which collapses an icon-only PrimeVue button (celer-06).
- **Content**: pass pre-translated `content` for plain text, or use the default slot for rich markup (slot wins over `content`).
- **Single open popover**: coordinated via `useSingleTip` — opening a tip closes any other open tip.
- **Default `aria-label`**: uses `common.tip` i18n key when `ariaLabel` is omitted.

## Prop Contract

- `tipKey`: **required.** An anchor key declared in `TIP_ANCHORS` (`@Constants`). Not an i18n key.
- `ariaLabel?`: override for the trigger button label (defaults to `$t('common.tip')`).

There is no `content` prop and no default slot. A tip's text has exactly one source — the
database — and a second way in would be a tip nobody can find or edit in the Dicas view.

## Editing

With `tips.manage`, the popover grows a pencil that closes the popover and calls
`tipStore.requestEdit(tipKey)`. The dialog itself is `OTipEditDialog`, mounted ONCE in
`TheLayout` — never one dialog per tip.

## Do Not

- Do not pass i18n keys or literal text as `tipKey` — it is an anchor.
- Do not add an anchor at a call site without adding it to `TIP_ANCHORS`;
  `tipAnchor.constant.spec.ts` fails the build for it, which is the point.
- Do not nest multiple `Popover` instances for tips — use `ATip` everywhere.
- Do not import `Popover` in consumers — `ATip` owns the popover lifecycle.
