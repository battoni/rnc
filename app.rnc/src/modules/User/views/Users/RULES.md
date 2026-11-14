---
version: 2.4.0
origin: pendulum
based-on: 2.3.1
---

# RULES — UsersView

> For AI agents. Last updated: 2026-09-18.

## Purpose

User management page and the reference for a **managed list**: a server-paginated `OListDivided`
with `OPaginator`, add/edit dialog, and archive/delete confirm popups.

## Intentional Decisions

- **The list is a page, not the table.** `usePaginatedList` owns `items`, `page`, `total` and
  `loading`; the view passes `fetchUsers` and renders. A sort or search change goes back to page 1
  (`reloadFirstPage`).
- **Row changes go through the paginator**: `prependItem` on create, `removeItem` on delete, a
  plain `map` on edit and archive (the count does not change).
- **A create while a search is applied reloads page 1** instead of prepending: the new account was
  not in the search, and a row above the matches reads as a broken search. "Applied" is
  `appliedSearch` — the ids the last request SENT — not names picked and not yet searched. Writing `items` to add or remove a
  row leaves the pager offering a page that no longer exists.
- **The search options are their own bounded request** (`per_page: 100`, the API ceiling). The
  first page cannot feed a search across every account, and a product with more accounts than the
  ceiling needs a server-side search, not a bigger page.
- **`displayUsers` maps each `User` onto the organism's roles**: name as title, `username` as
  subtitle, role as a `contrast` badge (a category, not a state), status as a success/danger tag.
- **Archive/delete live in `#actions`, not in `canArchive`/`canDelete`**: the confirm popup needs
  the clicked element as its anchor, and the built-in buttons emit only the item. `variant` says
  whether the slot renders in the row (icon) or in the ⋮ menu (labeled).
- **`#actions` mounts only when `canArchive || canDelete`.** `OListDivided` treats a present slot
  as "this row has actions" and shows the ⋮; a viewer with only `users.read` would otherwise open
  an empty menu. Edit still rides on `:canEdit` and does not need the slot.
- **Delete first, the status toggle last.** Delete shows only for an inactive account; a toggle
  placed before it would move under the cursor while its own confirmation is open.
- **An administrator has no toggle on their own row** (`isSelf`), and the form locks their own
  status. The API refuses self-deactivation too; the screen does not offer what will be refused.
- **A 422 is a warning in the person's language** (`onRequestRefused` + `translateError` on the
  key the API sends). Only what is not a 422 is the red "something went wrong".
- **`onArchiveAccept` / `onDeleteAccept` are local function declarations inside their parent
  handlers**: they close over the row without extra params. Do not extract them.
- **Inline skeleton rows**: the loading state mirrors the list's row shape, inlined on purpose.
- **Permissions computed from `userStore.hasPermission`** here, never inside the organism.
- **Create-user CTA is `#primaryAction` via `APageHeaderAction`**: icon-only below `md` so the
  title + tip fit one phone row. Do not put a labeled create button in `#actions` on this screen.

## Load failure

- **A failed load shows `users-load-error`** — `users.loadError.title` and a **Try again** that calls
  `load` — never the `AEmptyState`. The empty state asks the person to create the first user, which
  is the opposite of what a 500 needs. It reads `loadFailed` from `usePaginatedList`.
- **The skeleton is `rounded-lg`**, the radius `OListDivided` draws; it used to be `rounded-xl`, and
  the corner changed when the load finished.

## Dependencies & Context

- **`MOrderBy`**: bound with `v-model:orderBy`; the watch reloads page 1.
- **`MMainDialog`** is keyed on `editingUser?.id ?? 'new'` to reset the form.

## Do Not

- **Do not write `items.value = [...]` to add or remove a row** — use `prependItem`/`removeItem`.
- **Do not feed the search from the current page.**
- **Do not merge archive/delete confirm groups** — they show different messages and action labels.
- **Do not mount `#actions` unconditionally** — a present slot alone makes the ⋮ appear.
