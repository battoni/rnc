---
version: 2.1.0
origin: pendulum
based-on: 2.1.0
---

# RULES — Idea Module

> For AI agents. Last updated: 2026-09-17. Module-specific decisions; the app.rnc rules and `src/modules/RULES.md` are not
> repeated here. The API contract lives in `api.rnc/app/Modules/Idea/RULES.md`.

## Domain

**Ideias** (en: **Ideas**): the product's own feedback board. Three views — `IdeasView`
(`/ideias`) is the board, `IdeaView` (`/ideias/:id`) is one idea with its conversation and the
maintainer's controls, and `NewsView` (`/novidades`) is Novidades, the changelog.

**In identifiers the word is `Idea`; in prose it is "ideia"/"idea".** Same word in two languages,
the identifier/interface split the bootstrap uses elsewhere.

## The name can collide with the product's own vocabulary, and the placement is the answer (CRITICAL)

"Idea" is a common word, and a product built on this bootstrap may already use it for something of
its own — the project this module came from used it for a goal not yet planned, which is about the
person's work in the product, not about the product. **This board is about the app itself**, and
nothing on screen may let the two be confused.

Three things keep the two apart, and none of them is decoration:

1. **The nav entry lives in the `settings` list of `TheNavbar.vue`** — the settings footer, next to
   Users and Roles — and **never in the main rail** beside the product's own screens. It is an
   upstream entry, so it is not in `navItems.local.ts`: `localNavSettings` is where a PROJECT
   appends its own settings rows, after these.
   The position itself says "this is about the app, not about your work in it".
2. **The page title is the `ideas.title` key, and the bootstrap sets it to plain "Ideas".**
   A project that wants "Ideas for <product>" says so in its own `locales/local/` override —
   naming the product is the project's job, and the bootstrap has no product to name.
3. The routes are `/ideias` and `/novidades`, kept apart from the product's own paths; a product
   whose screens want either path has a clash to settle on purpose, not by reusing it.

Moving this into the main navigation reintroduces the ambiguity. Don't.

## Intentional decisions

- **Ids are numbers.** The API sends every id as an integer; `:id` and `?entrada=` are converted
  once with `toRouteId` where the view reads them. A comparison never stringifies the other side.
- **`canEdit` comes from the API and is NEVER recomputed here.** `Idea::isEditableBy()` on the
  server is the one definition; the card shows its pencil from that flag. A second copy of the
  rule in the client is how a visible button and a 403 start disagreeing.
- **Voting is not optimistic.** The count is the board's ordering and its social signal; a number
  that jumps and then corrects itself is worse than one that waits. Both vote endpoints answer
  with the whole idea, so the card lands on what the server actually holds.
- **`applyIdeaSaved` MERGES over the stored row, never replaces it.** A write response does not
  always carry every relation the board query eager-loads, and a straight replacement blanked the
  author on the card until the next full read. (Playbook trap §2.3, met again here.)
- **There is exactly ONE place that creates an idea**: `OIdeaCaptureDialog`. The board's CTA opens
  that same global dialog rather than a second local one, so the form and its success handling
  cannot drift apart. The board keeps its own dialog only for EDITING.
- **Navigating away closes the capture dialog.** It stays mounted in `TheLayout` so it can open from
  any authenticated screen, but a change to `route.fullPath` — or the host being torn down when
  the next view mounts its own `TheLayout` under App.vue's out-in fade — calls `closeCapture()`.
  A watch alone is not enough: the old instance dies and the new one remounts with the store still
  open. "From any screen" is about where you open it — not about carrying an unfinished draft
  across routes.
- **`ensureIdeasLoaded` shares the in-flight promise.** The board mounting, the detail view wanting
  the merge list, and a filter landing in the same tick cost one request.
- **Typing is debounced (300 ms); picking is not.** The board reloads from the API on every filter
  change, so an un-debounced text input fired one request per keystroke — fifteen for "total da
  semana", each replacing the list. `MIdeaFilters` holds a local `searchDraft` so the field stays
  responsive while the request waits. The selects stay immediate: a discrete choice is finished the
  moment it is made.
- **Only the newest board request may write the list.** Filters change faster than the network
  answers, so a slow response can land after a fast one and leave the board showing results for a
  query nobody is looking at. `loadIdeas` stamps each request and drops the stale ones — including
  their failures, which would otherwise empty a list a newer request had just filled.
- **The board does not paginate** (the API caps at 200). A product's feedback board is small and
  the filters narrow it; infinite scroll on a list people read top to bottom buys nothing.
- **Pickers that choose an idea ask the API, never the board.** `ideaStore.ideas` is whatever the
  last filter left behind, so the merge dialog and Novidades' credit field would silently offer a
  narrowed list — with nothing on screen explaining why the obvious candidate is missing. Both
  fetch their own candidates when they open.
- **The merge dialog offers only targets the API accepts** — not this idea, not a hidden one, not
  one already merged (`mergedIntoId`). A refusal that still happens (a race) is a 422 naming
  `ideas.merge.sameIdea` / `targetUnavailable` / `alreadyMerged`, shown as a `warn` toast with the
  translated rule, never the red error.
- **Novidades is a grid of cards, one column on a phone.** The month heading heads the grid; it is
  not a cell in it. Cards size to their content — stretching them to the row's height aligned the
  footers and bought a 180px hole inside every entry with no cover.
- **Applause lives on the card AND in the article, and it is the same request.** The list is patched
  with whatever comes back, so a clap given on the card is already counted when the article opens a
  second later. `MNewsReactions` repays its optimistic loan when the `reactions` OBJECT changes
  identity, so a failure must hand back `{ ...entry, reactions: { ...entry.reactions } }` — a
  shallow copy of the entry alone carries the same counts object and repays nothing.
- **After a reaction 429, `NewsView` cools the buttons for 3s** (`reactionsDisabled` on card/article)
  so a hammering thumb does not open a toast per click. Cleared on unmount.
- **A missing idea (404) toasts then redirects to the board**; a missing shared Novidades `?entrada=`
  toasts and strips the query. Silent empty screens looked like failures of the page itself.
- **But only when the list actually arrived (CRITICAL).** `loadNews` swallows its own failure and
  RESOLVES, so `openSharedEntry` runs after a network blip with an empty list and every id looks
  missing. It bails on `hasNewsLoadError`: the screen already says "could not load", and stripping
  the query on top of that destroys the shared link the Retry button was about to need.
- **The idea store exposes `$reset`** so logout can wipe lists and “already loaded” flags without
  throwing on a setup store.
- **Create CTAs on Ideias and Novidades use `APageHeaderAction` in `#primaryAction`.** Icon-only
  below `md` so the title row stays one line on a phone; labeled from `md` up. A full-label
  `Button` there crushed long titles the same way Perfis did.
- **Official reply and maintainer note use panel surfaces, not primary-50 tints.** Light brand
  tints stay lima in dark scheme and wash out `text-body`. Official comments and the admin note
  use `bg-panel-muted` + `border-primary-500`; the official badge is `bg-primary-500
text-primary-contrast`.
- **Reaction state is relief, never a border.** Unspent is raised, spent is pressed in. See
  `styles/theme/RULES.md`: a border that draws a box around a surface is replaced by relief.
- **A cover that fails to load drops its box, and its `alt` is empty.** A 404'd cover rendering
  `alt="{title}"` is how the entry's title ended up as bare text sprawling outside the card.
- **Novidades groups by a `YYYY-MM` key (`monthKey`), never by the localised label** — the label
  changes with the locale and would split a month across two headings on a language switch. The
  key is cut on the reader's clock, like the label: slicing the UTC string put a late-evening entry
  under next month's key with this month's label.
- **A failed load is not an empty board.** Both views read their `has…LoadError` flag and show the
  failure with **Try again** (`ideas.loadError.*`); neither empty state — the board's, the filter's
  — may render while it is up.
- **Timestamps here are real instants** and `new Date(iso)` is correct — unlike a plain
  `YYYY-MM-DD` day, which has no zone and must not go through `new Date(day)` (see
  `helpers/localDate.helper.ts`). `useIdeaFormat` owns the difference.

## "Something you supported moved" — quiet on purpose (CRITICAL)

`useIdeaStatusWatch` marks the ideas the viewer SUPPORTS whose status changed since they last
looked: a dot on the card, and one plain sentence above the list.

- **Never a red badge, never a count in the navigation, never a notification.** The brief rules out
  urgency, alarm, streaks and guilt. Someone who never notices the dot loses nothing, and that is
  the design, not a limitation.
- **The memory is `localStorage`, not a table.** It records what THIS person has already looked at:
  it does not need to survive a new browser, it must never be shared, and putting it on the server
  would mean a write on every page view.
- **Every storage access is wrapped.** A private window, cleared site data, or a browser set to
  block storage all throw, and a marker failing must never take the board down with it. A viewer
  who cannot store anything simply never sees the dot.
- **Only ideas the viewer supports, and only after a first sighting.** An idea first met in a state
  that is not `received` has not "changed" — nobody was watching it yet.
- **Opening the idea is what acknowledges it.** Nothing else clears the mark.

## Delivering an idea offers the changelog entry

Setting a status to `delivered` opens the announce dialog, pre-filled with the idea's title and the
public answer, crediting the idea. It is what keeps Novidades from being a second job: the entries
that answer an idea are exactly the ones somebody is waiting for, and asking the maintainer to
retype them elsewhere is how they stop being written.

**It is an OFFER.** Nothing is published automatically — an entry nobody chose to publish is not an
announcement.

## The capture dialog is mounted by `TheLayout` (CRITICAL)

`src/layouts/TheLayout/TheLayout.vue` renders `<OIdeaCaptureDialog />`. That is the ONLY place this
module reaches outside its own folder.

It has to be there: the dialog must exist once above every authenticated screen, because the
moment somebody has something to say about the product is the moment the product annoyed them, and
a board they must navigate to first is a board they reach after the feeling has passed.

In rnc the line is part of `TheLayout` itself, so it does not conflict. The paragraph that said
it "will conflict on every `cortex/sync`" was written in the project this module came from, before
rnc had an overlay slot; a project's OWN overlay goes in `TheLocalOverlays.local.vue` now.

## Accessibility

The board is a list of other people's writing operated almost entirely by small controls — a vote
arrow, a status chip, five filters. That is the shape that ends up unusable without a mouse unless
somebody checks. **Known gap:** the e2e that pinned this (`accessibility.spec.ts`) stayed in the
project the module came from and was never ported — nothing in this repository checks the four
rules below today.

- **Every filter carries an `aria-label`.** A placeholder is not an accessible name: it disappears
  the moment anybody types.
- **The card is named by its idea** (`aria-label` on the `<article>`), so a screen reader announces
  which one it is rather than "article".
- **The vote button is a toggle and says which way it is set** (`aria-pressed`). Its name is fixed
  — "Support this idea (3 supporters)" — so it says what the number counts, and does not flip with
  the state, which `aria-pressed` already announces.
- **The moved marker carries `role="img"` and an `aria-label`.** It is a coloured dot and nothing
  else; without a name the whole "something you supported moved" idea reaches only sighted readers.
  A `title` on a `<span>` is not a reliable substitute.

## Colours

Status colours live in `IDEA_STATUS_META` and nowhere else.

- **`not_now` is `contrast`, NEVER `danger`.** Declining is a scoping decision; error red turns it
  into a verdict on whoever wrote the idea, in a product whose brief says the system does not
  judge. `AIdeaStatusTag.spec.ts` asserts this.
- **`received` has no hue** (`bg-panel-muted text-muted`): giving "nobody has looked yet" a colour
  would make it look like a state somebody chose.
- The supported vote button is a filled brand button and takes **`text-primary-contrast`** — never
  a raw `text-ink-*`, which measures 3.08:1 on terracotta.
- **No text in a primary shade.** The ramp does not flip: primary-500/600/700 measured 1.2–1.9:1 on
  the light surfaces. Readable text takes a role token (`text-heading`, `text-body`, `text-muted`),
  and a link says it is one with an underline. Primary stays on fills and borders.

## Attachments

- One image, `IDEA_ATTACHMENT_ACCEPT` (jpg/png/webp — **no SVG**), `IDEA_ATTACHMENT_MAX_BYTES`
  (2 MB). Client-side validation here is a courtesy so nobody uploads two megabytes to be told no;
  **the server is the boundary**.
- **Writes are always multipart**, and the update is `POST` with `_method=PATCH`: PHP only
  populates `$_FILES` for POST, so a genuine multipart PATCH arrives with the file silently
  missing. `toIdeaFormData` in `helpers.ts` is the single body builder.
- `removeAttachment` travels only when TRUE — FormData stringifies, and "sent no file" must stay
  distinguishable from "delete the file".

## Testing traps met here

- **Auto-imported children are NOT registered in the unit harness.** An unregistered child renders
  as an unknown element and assertions about its output pass over nothing. Register them by hand
  in `global.components` (see `MIdeaCard.spec.ts`).
- **The harness locale is `en`** (`VITE_DEFAULT_LANGUAGE`), so a spec asserting translated text
  must assert the English string and say so.
- **Mock `vue-router` PARTIALLY.** Replacing the module wholesale breaks everything in the graph
  that needs `createRouter` — the store reaches the API provider, which reaches the real router.
