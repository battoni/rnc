---
version: 1.3.0
origin: pendulum
based-on: 1.3.0
---

# RULES — Tip Module

> For AI agents. Last updated: 2026-09-17.

## Purpose

Managing the app's contextual help: the Dicas view, the add/edit form, the history dialog with
restore, and the single edit dialog that every `<ATip>` in the app opens.

## Anchors (CRITICAL)

A tip is bound to an **anchor** — a declared place in the UI where `<ATip tip-key="...">` renders.
The catalogue is `TIP_ANCHORS` in `@Constants`, NOT in this module and NOT in the API: anchors are
the call sites, and the call sites live across `src/`.

- **Creating a tip means picking a free anchor from a dropdown.** Never a free-text key. A tip with
  no place to render is content nobody will ever read and nobody will think to delete.
- **One active tip per anchor.** The form filters `takenAnchorKeys`; the API enforces it.
- **Archiving frees the anchor**, so the dropdown offers it again.
- `tipAnchor.constant.spec.ts` walks the `.vue` files in both directions — an anchor renamed on one
  side only fails the build. That test is the feature's safety net; do not weaken it to make a
  rename easier.

## "See in app" link

Every row links to the screen its tip renders on, built from the anchor's `routeName` +
`routeQuery`, and from nothing else.

**The control answers the viewport on the inline strip, and the list's `variant` in the ⋮.**
Below `useViewport`'s `isMobile` the inline control is icon-only (`rounded` + `aria-label`),
because a labelled button fights the title for width on a phone. At or above it, the label is
shown, because a lone arrow icon does not say where it goes — that was found in review once
already and written into the spec, so a change that drops the label at every width will fail
`TipsView.view.spec.ts` on purpose.

**In `OListDivided`'s overflow menu (`variant === 'menu'`), every custom action — History,
Archive/Restore, and See in app — carries a `:label` and `w-full justify-start`.** The built-in
Edit already does; icon-only custom rows beside it made the ⋮ the only action strip on a phone
and left it unreadable. Inline stays icon-only for History/Archive; See in app still follows
`isMobile` there.

`aria-label` is present on icon-only shapes. What a screen reader hears must not depend on how
wide the window happens to be.

- **Archived rows keep the link.** Where a tip used to sit is exactly what you check before
  reactivating it.
- **A destination is a route, not an entity.** The project this came from also had anchors bound to
  one specific record — a tip living on a single product category's screen — and resolved the id by
  loading that catalogue from this view. The bootstrap models the anchor, not the things a product
  might anchor to: a project that needs entity-scoped anchors resolves the params in its own local
  catalogue, and this view stays ignorant of what a product's entities are.
- **An anchor may carry a `routeQuery`** — the screen it points at is free to read it and open on
  the right tab. No anchor in the bootstrap uses one yet; the field exists because a project's own
  anchors will.

## History (CRITICAL)

- The history is an **event log**: `created`, `updated`, `restored`, `archived`, `unarchived`.
- **Restore appends a new version; it never rewinds.** The dialog therefore reloads after
  restoring, and the version restored _from_ stays visible.
- **"Current" is the newest CONTENT-bearing entry, not the newest row.** Archiving appends a row
  without changing a word — marking that one current would tell the reader the tip was last
  rewritten when it was only taken down.
- The "restored from…" label is built from `restored_from_id`, not from a stored sentence, so it
  renders in the reader's language rather than the restorer's.

## Editor

PrimeVue `Editor` (Quill), toolbar declared through the `#toolbar` slot — **not** `:modules`, which
makes Quill build its own toolbar while PrimeVue still renders its default template, leaving two
rows with one of them dead markup.

**The toolbar mirrors the API's `TipContentSanitizer` allow-list and never exceeds it.** A button
that inserts what the sanitiser then discards is worse than no button: the author sees it work,
saves, and finds it gone. No image button.

## Change reason

- Demanded when the **content** changes, and when **archiving**.
- Not demanded to move a tip to another anchor: that rewrites nothing anyone read, and asking for a
  sentence anyway trains people to type "." to get past the field.

## Refusals (422)

The API refuses with an i18n key under `tips.errors.*` (`anchorTaken`, `contentRequired`,
`reasonRequired`), and the keys live in `src/locales`. A 422 is information, not a failure:

- **In `MAddEditTipForm`** it goes under the field it names (`anchor_key`, `content`,
  `change_reason`; any other field under the form), in `text-danger-700`, with no toast.
- **From a row action in `TipsView`** (archive, unarchive onto a place already taken) it is a
  `warn` toast with `translateError(key)`. Only what is not a 422 is the red toast.

## Status tag

An archived row is `danger`, an active one `success` — tones `OListDivided` paints. `secondary`
is not one of them, and a tone it does not know renders PrimeVue's bare default Tag (1.83:1).

## Dialogs

- `OTipEditDialog` is mounted once by `TheLayout` and driven by `tipStore.editingAnchorKey`. It
  fetches the management list on demand — that payload has no business loading for every user on
  every session just in case someone edits a tip.
- Archiving uses an `MMainDialog`, not a `ConfirmPopup`, because the reason is mandatory and a
  popup cannot gate its accept button on a field. Row-level actions elsewhere still use
  `ConfirmPopup`.
- **Create-tip CTA is `#primaryAction` via `APageHeaderAction`** on `TipsView` — not `#actions`.
  Same phone-row contract as Usuários and Perfis.
- **The form's footer buttons are `gap-3` apart** — at 8px their grown touch areas overlap.

## Route

- **`/tips` and `/dicas` carry `meta: { permission: 'tips.read' }`** — the same key the nav item and
  `GET /tips` ask for. The nav filter only hides the link; the route guard is what closes the screen.

## Do Not

- Do not render tip content anywhere it did not come from `GET /tips/active` or `GET /tips`. It is
  `v-html`, and it is safe only because the API sanitised it on write. It always goes through
  `withExternalLinks` (`@Helpers`): the sanitiser strips `target`/`rel`, and a link that opens in
  the same tab takes the person off the screen the help is for.
- Do not refetch the list after create/update/archive/restore — the API returns the entity; update
  the local ref and call `tipStore.applyTip` so the rest of the app follows.
- Do not throw a refusal key back as a sentence, or show it in a red toast — see Refusals.
- Do not add a hard delete. Archiving is the removal; the log outlives the text.
