# Changelog — User Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 2.5.0 — 2026-11-14

### Changed

- Chrome QA 5.2: `UsersView` routes are the operator's role's (`meta.role`), as the rail's Operador group is.

## 2.4.0 — 2026-11-14

### Added

- rnc-4 verify P2-S5: `endViewAs()` — sets `platform.view_as` to null once the API closed the view-as, so the banner does not outlive it when the following `fetchMe` fails.

## 2.3.0 — 2026-11-14

- View-as modules (rnc-4 F7, W93): `UserPlatform.view_as` carries the viewed firm's `modules`, and `hasModule(key)` answers from them while a view-as is set, so the rail offers the viewed firm's opt-in screens.

## 2.2.0 — 2026-11-14

- Session firm and modules (rnc-4 F3c, R20): `useUserStore` keeps `firm` and `platform` from `auth/me` and the sign-in answers (`User.firm`, `UserFirm`, `UserPlatform`); `hasModule(key)` answers for the firm's opt-in modules, none for a firm-less account. A payload without `firm` (a profile save) keeps what the session knew; `clearUser` drops both.

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
