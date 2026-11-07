# Changelog — User Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.1.0 — 2026-09-17

### Fixed

- `MAddEditUserForm` (1.4.1): the dialog's gap filler (`absolute bottom-0 w-full`) had no `left`, so it sat at its static position 20px in and ended 20px past the sheet; on a phone the page grew to 410px, the browser zoomed out, and the dialog covered 370 of 390px with a strip of page on the right. It takes `left-0` (`src/test/dialogGapFiller.spec.ts`).

### Added

- `CreateUserPayload`, `UpdateUserPayload` and `GetUsersParams` leave through the barrel: they moved from the service files (inline `export interface`) to `interfaces.ts`.

### Changed

- `store.ts` exports `useUserStore` in a single export block at the end.
- Every service names its default function after its file (`export default async function getUsers(`). The barrel is unchanged.

## 2.0.0 — 2026-09-14

### Breaking

- `GetUsersService` answers a page (`PaginateResult<User>`) and takes `page`, `per_page` and `profile_type`.

### Added

- `User` carries `email`, `email_verified`, `phone` and `profile_type`.

### Removed

- `MUserCard` — `UsersView` lists with `OListDivided`, and nothing else used the card.

## 1.1.0 — 2026-05-30

### Added

- Unit tests (`store.spec.ts`).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
