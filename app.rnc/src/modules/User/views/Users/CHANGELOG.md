# Changelog — UsersView

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.4.0 — 2026-11-14

### Changed

- Chrome QA 5.2: the routes ask `meta.role: 'operador'` besides `users.read` — the firm manager holds `users.read` for its team (rnc-4 F5a) and runs it at /gestao/equipe; this screen is the operator's group's, and `attachAuthGuard` sends anyone else home, as the rail hides it.

## 2.3.1 — 2026-09-18

### Fixed

- `#actions` mounts only when the viewer has `users.archive` or `users.delete`. A present slot made
  `OListDivided` show the ⋮ even for a read-only viewer, whose every button failed its `v-if` —
  empty menu. Specs cover slot absent with only `users.read` and present with `users.archive`.

## 2.3.0 — 2026-09-17

### Fixed

- A failed load rendered the empty state ("No users yet" + "Add User"), telling the person their users were gone. It now renders `users-load-error` with `users.loadError.title` and a Try again button that reloads (chapaja-247, lifeshifter-350). The integration spec that only checked "no rows" now requires the failure state, and a retry that loads.
- The loading skeleton was `rounded-xl` while the list it stands for is `rounded-lg`; the corner jumped when the load finished (lifeshifter-482).

## 2.2.0 — 2026-09-16

### Fixed

- Creating a user while a search filters the list reloads page 1 instead of prepending a row the search does not match (chapaja-224). `appliedSearch` records what the last request sent. Two new specs.

## 2.1.0 — 2026-09-16

### Changed

- Branches on `HTTP_STATUS` instead of a bare status number. The same code appears in eight files, and a reader had to rebuild what the API means by it every time (from largatudoevai).

## 2.0.2 — 2026-09-15

### Fixed

- Create-user CTA moved to `#primaryAction` as `APageHeaderAction` (icon-only on phone), matching
  Perfis and freeing the title row at 360px.

## 2.0.1 — 2026-09-14

### Fixed

- Both routes declare `meta.permission: 'users.read'`. The nav hid the link and the API refused the list, but typing the address opened a screen that could only fail (from lifeshifter).
- The search MultiSelect sits in a `FloatLabel variant="on"` like `MOrderBy` beside it, with a visible label instead of a placeholder and an `sr-only` label (from lifeshifter).
- `<aside class="contents">` for the overlays. The aside renders nothing in flow, but as a box it is still a flex child of `TheLayout`'s content column, whose `gap-8` reserved 32px under the page for it (from lifeshifter).

## 2.0.0 — 2026-09-14

### Changed

- Server-paginated with `usePaginatedList` + `OPaginator`; the card grid became an `OListDivided` with a details dialog (role, username, e-mail and whether it is verified, phone) (from chapaja).
- The signed-in administrator has no status toggle on their own row; 422 refusals arrive as a translated warning.
- The search options come from their own bounded request instead of the loaded list.

## 1.4.0 — 2026-06-13

### Changed

- Use AEmptyState for the empty list; remove the unused ThePageHeader icon.

## 1.3.0 — 2026-06-01

### Added

- `data-testid="add-user"` on the add Button (stable e2e selector).
- Edit-replaces-row and delete-removes-row integration tests.

## 1.2.0 — 2026-05-30

### Added

- Integration tests (`UsersView.view.integration.spec.ts`).

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`UsersView.view.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
