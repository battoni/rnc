# Changelog — Idea Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.1.0 — 2026-09-17

### Added

- `ideas.merge.sameIdea`, `ideas.merge.targetUnavailable` and `ideas.merge.alreadyMerged` in both locales — the keys the API now sends for a refused merge (api.rnc ARC-10); the screen showed the raw key before.

### Fixed

- The "planned" status chip (`IDEA_STATUS_META` in `constants.ts`) wrote `primary-700` on `primary-100`, 1.83:1 in `battoni-dev`; it writes `primary-950` (4.54:1), the step a pale brand tint takes (theme RULES). `src/test/brandTintText.spec.ts` guards the pair.
- `IdeaView`: the merge dialog offered ideas already merged into another, which the API refuses (`targetUnavailable`); `mergeOptions` now leaves them out, beside hidden ones. A refused merge (422) is a `warn` toast with the translated rule, not the red "something went wrong". Covered by `IdeaView.view.spec.ts`.
- `MIdeaForm` and `MNewsForm` footer buttons, and `MIdeaModerationForm`'s Save / Hide / Merge row, `gap-2` → `gap-3` (VIS-15). `src/test/moduleFooterGap.spec.ts` scans every module form footer; `MIdeaModerationForm.spec.ts` is new.
- `MIdeaForm` (type, area), `MNewsForm` (areas, credited idea) and `MIdeaModerationForm` (status) put `id` on a `Select`/`MultiSelect` root, so the `<label for>` focused nothing; they take `inputId` (lifeshifter-471).

### Changed

- `MNewsArticle` (1.3.4) renders the body through `withExternalLinks` (`@Helpers`, tip-002) instead of a DOM pass after mount; links still open in a new tab with `rel="noopener noreferrer"`, now by the same helper `ATip` uses. `MNewsArticle.spec.ts` pins it.
- `getIdeas.service.ts` sends its filters through `query` instead of building the string by hand; `mine` travels as `1` only when on, as before. Wire unchanged; `getIdeas.service.spec.ts` is new.
- `IdeasView` and `useIdeaStatusWatch`: local working variables in camelCase (`filters`, `seen`, `next`); `NewsView`'s one-line computeds moved into the one-line block (CEL-19). No behaviour change.

## 2.0.2 — 2026-09-17

### Fixed

- `IdeasView` read only the list, so a failed load showed "nothing here yet" and the CTA to write the first idea. It now reads `hasIdeasLoadError` and shows the failure with **Try again** (`ideas.loadError.*`), never an empty state on top (from lifeshifter-159).
- `NewsView` rendered "the filter emptied the page" under "could not load": `hasNothingForFilter` now excludes the failed load (from lifeshifter-189).
- `useIdeaFormat.monthKey` cut the key from the UTC string while `formatMonth` used the reader's clock; an entry from 30 September at 22:00 in São Paulo sat under October's key with September's label. The key is now `toLocalDateString(...)` (from lifeshifter-194).
- `AIdeaVoteButton` had no name of its own and was announced as a bare number; it now carries a fixed `aria-label` with the action and the count (`ideas.actions.supportCount`), and `aria-pressed` keeps saying which way it is set (from lifeshifter-164).
- `MNewsReactions` created seven confetti nodes per click under reduced motion, which the CSS hid, so their `animationend` never came and they piled up until unmount; the script now throws none. Its buttons carry `touch-target`, sit `gap-3` apart, and the spent count is `text-heading` instead of `text-primary-700` (1.9:1 on the light panel) (from lifeshifter-594, lifeshifter-183, CEL-01).
- `MIdeaCard`'s clickable title carries `touch-target`, `gap-3` from the controls beside it (CEL-17).
- `IdeaView`'s "maintainer answer" label is `text-muted` instead of `text-primary-500` (1.16:1 on the light panel-muted) (CEL-01).
- RULES said the nav entry lives in `localNavSettings`; it is an upstream row in `TheNavbar.vue`'s `settings` list (from lifeshifter-153).

### Changed

- Every service names its default function after its file (`export default async function getIdeas(`), per the service pattern. The barrel is unchanged (CEL-11).

## 2.0.1 — 2026-09-16

### Fixed

- RULES cited `e2e/ideas/accessibility.spec.ts`, which was never ported (the Added line for it below describes the source project, not this repository); the accessibility section now records it as a Known Gap.
- `useIdeaFormat`'s comment named "the LifeShift modules"; it states the rule about calendar days instead.
- RULES argued the board's placement from LifeShift's method (`brand/BRIEF.md`, "Alvo") and named "LifeShift screens"; the section states the general collision instead.
- RULES said the `TheLayout` line "will conflict on every `cortex/sync`" and must be re-added — the source project's situation, not rnc's, where `TheLayout` is upstream and `TheLocalOverlays.local.vue` exists.

## 2.0.0 — 2026-09-16

### Changed

- **Breaking:** every id in `interfaces.ts` is a `number` (`Idea`, `IdeaAuthor`, `IdeaComment` incl. `ideaId`, `mergedIntoId`, `NewsEntry` and its `idea`, `NewsReader`, `NewsPayload.ideaId`), and every service, store action and emit that takes one takes a number. The API sends integers now (from lifeshifter-269).
- `IdeaView` reads `:id` and `NewsView` reads `?entrada=` through `toRouteId` (`@Helpers`) — the one place a string becomes an id. A comment is the viewer's own when `author.id === user.id`, without the `String()` on both sides. `NewsView.view.spec.ts` opens a shared link by a numeric id and goes red if the query value is compared unconverted. New `IdeaView.view.spec.ts`: the idea is fetched by the numeric route id, and delete is offered on the viewer's own comment only — red with the old `String()` comparison and red with the raw param.

## 1.7.1 — 2026-09-16

### Fixed

- Type badge, idea card and idea view meta rows, comment timestamp, idea-form and moderation hints, news readers count: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.7.0 — 2026-09-16

### Changed

- Branches on `HTTP_STATUS` instead of a bare status number. The same code appears in eight files, and a reader had to rebuild what the API means by it every time (from largatudoevai).

## 1.6.5 — 2026-09-15

### Fixed

- Ideias and Novidades create CTAs use `APageHeaderAction` in `#primaryAction` (icon-only on
  phone). Labeled `Button`s left “Nova entrada” / “Nova ideia” in full on small screens and
  competed with the title row.

## 1.6.4 — 2026-09-15

### Changed

- RULES / `OIdeaCaptureDialog`: host teardown is App.vue's out-in remount of each view's
  `TheLayout`, not a `:key="route.fullPath"` on RouterView (that key was retired — it paired with
  a simultaneous fade that invented global page scrollbars).

## 1.6.3 — 2026-09-15

### Fixed

- Official comment (`MIdeaComment`) and the maintainer note on `IdeaView` use `bg-panel-muted` +
  `border-primary-500` (badge `bg-primary-500 text-primary-contrast`) instead of primary-50/100/200
  tints. Those tints stay light lima in dark scheme and left body/heading text unreadable.

## 1.6.2 — 2026-09-15

### Fixed

- `OIdeaCaptureDialog` closes when `route.fullPath` changes, and when its host unmounts. App.vue
  keys RouterView by `fullPath`, so navigation remounts `TheLayout`; a watch alone died with the
  old instance while `isCaptureOpen` stayed true and the new dialog opened again. `onBeforeUnmount`
  clears the store. Covered by `OIdeaCaptureDialog.spec.ts`.

## 1.6.1 — 2026-09-14

### Fixed

- Free text wraps anywhere: the idea's title, description and maintainer answer on `MIdeaCard` and
  in `IdeaView`, and a comment's body in `MIdeaComment`. A run with no space and no hyphen has no
  break opportunity, and on a 390px phone the card's description painted 2209px past its own box —
  the same shape `MNewsCard` and `MNewsArticle` were fixed for. Covered by
  `e2e/ideas/board-overflow.spec.ts` (from lifeshifter).
- The attachment link on `IdeaView` is `rel="noopener noreferrer"`, like the news article's: `noopener` alone still sends the page's address to wherever the file is hosted (from largatudoevai).
- The News month headings start with a capital in pt-BR — "Setembro de 2026" — through `toSentenceCaseHelper`; `Intl` writes month names in lowercase there (from lifeshifter).

## 1.6.0 — 2026-09-10

### Fixed

- `toNewsFormData` sends the entry's areas. It never had: the form collected them, the card
  rendered them and the API validated them, but the field did not leave the browser — and the API
  reads an absent `areas` as "this request is not about areas", so every save answered 200 and
  filed the entry under nothing.

### Added

- `clearAreas` in the same builder, and `helpers.spec.ts` to hold both. Multipart cannot write an
  empty array, so removing the last tag was indistinguishable from not mentioning tags at all.

## 1.5.0 — 2026-09-09

### Fixed

- A shared `?entrada=` link survives a failed list load. `loadNews` swallows its failure and resolves, so the not-found branch ran with an empty list, called a good link missing and then stripped the query — leaving Retry with nothing to open.

### Added

- `NewsView.view.spec.ts` — covers the shared-entry branch and the reaction 429 cooldown, including the timer being dropped on unmount.

## 1.4.0 — 2026-09-09

### Changed

- Idea store exposes `$reset` for logout.
- Idea detail toasts on 404 before redirecting to the board; shared Novidades `?entrada=` missing toasts and clears the query.
- Reaction 429 starts a short UI cooldown on card/article buttons.
- Idea/news form sticky footers use `bg-canvas` instead of `bg-panel`.

### Added

- Store spec for `$reset`.

## 1.3.0 — 2026-09-01

### Changed

- **Novidades is a grid, not a stack.** At least two columns above the phone breakpoint, three on a
  wide screen, one column on a phone. The month headings still head each grid. Cards size to their
  content (`items-start`): stretched to the row's height they aligned the footers and paid for it
  with a 180px hole inside every entry that has no cover, and most entries have no cover.
- **The applause is on the card as well as in the article.** Somebody scrolling the changelog can
  clap without opening the entry. `onReactClick` therefore takes the entry it is applauding rather
  than reading the open article.
- **The reaction buttons are relief, not an outline.** Unspent is a raised surface (`shadow-lift`),
  spent is pressed into the panel (`shadow-sink`) — the theme's own vocabulary. They were bordered
  boxes with a `border-primary-500` when spent, which is the one thing `styles/theme/RULES.md`
  explicitly retires: a border draws a box around a surface, and this is a surface.
- **The card's three write actions moved behind one `⋮`** (`MNewsCard`), the pattern
  `ThePageHeader` already uses. The read count stayed on the card — it is information, not an
  action.
- **The idea's comments got the same chrome as the rest of the page.** The section sat bare on the
  canvas while everything above it was in a panel, which read as the page having ended.

### Fixed

- **A failed reaction never gave its optimistic count back.** `MNewsReactions` repays the loan when
  the COUNTS it was given change identity — it watches `reactions`, not the entry — and the catch
  handed back a shallow `{ ...entry }` carrying the very same `reactions` object. The reader kept
  looking at applause the server had refused.
- **A cover that 404s no longer dumps the entry's title into the layout.** Both the card and the
  article rendered `alt="{title}"`, so a dead URL put the headline in bare text outside the card.
  The alt is empty and the box leaves.
- **The board's filters fill the width on a phone.** The four selects were a fixed `basis-40` with
  no `grow`, so at 360px each dropped onto a line of its own and left 176px of dead canvas beside
  it — four rows of filter to reach a board.
- **One `h1` per page.** The idea's title and the article's title were both `h1` beside the one
  `ThePageHeader` renders.
- **The card's read count and its `⋮` sit 12px apart**, and the count opted into `touch-target`.
  Under `pointer: coarse` both grow their hit area 6px a side, so 4px between them left the areas
  overlapping with the later sibling — the menu — winning a thumb aimed at the audience.

## 1.2.0 — 2026-09-01

### Fixed

- **The board was not operable without sight.** The five filters announced nothing (a placeholder
  is not an accessible name), the cards announced only "article", and the moved marker was a
  coloured dot carrying just a `title` on a `<span>` — so the whole "something you supported moved"
  idea reached only sighted readers. All now carry proper accessible names.

### Added

- `e2e/ideas/accessibility.spec.ts` — six specs covering filter names, card names, the vote
  toggle's state, opening and supporting an idea from the keyboard alone, and the marker's name.

## 1.1.2 — 2026-09-01

### Fixed

- **A filter that matched nothing said "the board is empty".** Two distinct empty states now: one
  for a board with no ideas at all, one for a filter that found none, which offers to clear it.
- **Any error on the detail screen sent the reader back to the board.** Only a 404 does now; a
  network blip or a 500 shows the error and keeps their place.

### Removed

- `AIdeaVoteButton`'s `isDisabled` prop. Nothing passed it — everyone who can read the board can
  support an idea.

## 1.1.1 — 2026-09-01

### Fixed

- **A filtered board silently narrowed the merge dialog and the Novidades credit field.** Both read
  `ideaStore.ideas`, so a maintainer who had filtered the board was offered a short candidate list
  with nothing explaining the absence. Both now fetch their own candidates when they open — and
  Novidades asks the API for delivered ideas by status instead of filtering client-side.

## 1.1.0 — 2026-09-01

### Fixed

- **The search box fired one request per keystroke.** Typing is now debounced at 300 ms behind a
  local draft ref, so the field stays responsive while the request waits; the selects stay
  immediate.
- **A slow board response could overwrite a newer one.** `loadIdeas` now stamps each request and
  only the newest writes the list — stale failures included, which would otherwise empty a list a
  newer request had just filled.

## 1.0.1 — 2026-09-01

### Fixed

- The comment thread now tells hiding apart from removal, using the API's new `deleted` flag —
  `hidden` alone could not, because a removed comment was already hidden. The visible comment count
  moves only when something visible stopped being visible.

## 1.0.0 — 2026-09-01

### Added

- The Ideias board — three views (`/ideias`, `/ideias/:id`, `/novidades`), a store, seventeen
  services, three atoms, five molecules and the global capture dialog.
- **Quick capture from anywhere**: `OIdeaCaptureDialog` mounted once in `TheLayout`, opening with
  the area already answered from the current route via `ROUTE_TO_IDEA_AREA`.
- **Board** with status / type / area / free-text filters, two orderings, a "only mine" toggle, and
  granular card updates driven by the API's own responses.
- **Detail** with the screenshot, the maintainer's public answer, the comment thread, and the
  moderation panel (status + note, hide, merge) for `ideas.manage`.
- **Novidades**, grouped by ISO month, crediting the idea and its author when an entry answers one.
- pt-BR and en translations in `src/locales/local/`, nav entries in `navItems.local.ts`.
- **A quiet marker for movement**: `useIdeaStatusWatch` dots the ideas the viewer supports whose
  status changed since they last looked, remembered per-viewer in `localStorage`. No badge, no
  count in the navigation, no notification.
- **Delivering an idea offers the changelog entry**, pre-filled from the idea and crediting it —
  an offer, never automatic.
- 46 Vitest specs across the store, the atoms, the card, the quick-capture composable and the
  status watcher; 24 Playwright e2e specs across the board, capture, moderation and Novidades.

### Notes

- Adds one line to `src/layouts/TheLayout/TheLayout.vue`, a synced file. See RULES.md — it will
  conflict on every `cortex/sync` until rnc grows an overlay slot.
