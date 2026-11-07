---
version: 1.1.0
origin: pendulum
based-on: 1.1.0
---

# RULES — OPaginator

> For AI agents. Last updated: 2026-09-17.

## Purpose

The pager for every server-paginated list view. Wraps PrimeVue `Paginator` so views never
deal with PrimeVue's paging arithmetic. Always used together with `usePaginatedList`
(`@Composables`), which owns the state this component renders.

## Intentional Decisions

- **1-based pages at the boundary**: PrimeVue's `Paginator` works in a 0-based `page` and a
  record offset (`first`); the API and `usePaginatedList` work in 1-based pages. The
  conversion lives here and nowhere else — `first` is derived from `page`, and `@page` is
  re-emitted as `onPage` with `event.page + 1`.
- **Hidden on a single page** (`hasMultiplePages`): a list that fits in one page renders no
  pager at all, so short lists keep their previous appearance.
- **Fixed `template`** (`FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink`):
  no page-size selector and no jump-to-page input — page size is a server concern
  (`per_page`, clamped by `App\Helpers\Pagination`), not a user control.
- **The page report speaks the app's language.** `currentPageReportTemplate` is
  `common.paginator.report` ("Page {currentPage} of {totalPages}"); the two placeholders are passed
  back to PrimeVue untouched as named arguments. PrimeVue's default, `({currentPage} of
{totalPages})`, was English on every screen. The buttons' own names come from the PrimeVue locale
  (`locale.aria`, `libraries/primevue/locale.ts`).
- **44px buttons 12px apart under a finger.** `:pt` gives the four page buttons
  `pointer-coarse:min-h-11 pointer-coarse:min-w-11` and the content row `pointer-coarse:gap-3`; the
  mouse keeps PrimeVue's 40px/4px density. It is a bound `pt` object on purpose: Paginator hands its
  page-link children the `pt` prop only, so `pt:first="…"` attributes never reach them.
- **No loading state of its own**: the list organism above it renders skeletons; the pager
  simply re-renders when the new page arrives.

## Prop & Emit Contract

- `page` (1-based), `perPage`, `total` — all required, all supplied by `usePaginatedList`.
- `onPage(page: number)` — emitted with a 1-based page. Wire it straight to the composable's
  `onPage`; it refetches.

## Do Not

- **Do not use PrimeVue `<Paginator>` directly in a view** — always `OPaginator`, or the
  0-based/1-based conversion gets duplicated and drifts.
- **Do not mutate `items` directly alongside this component** — add and remove rows through
  `usePaginatedList`'s `prependItem`/`removeItem` so `total` stays in step with the pager.
- **Do not add a rows-per-page dropdown** without changing the server contract first.
