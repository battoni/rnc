# Changelog — App Helpers (ApiResponse, Pagination, Money, Brazil)

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.3.2 — 2026-09-22

### Documented

- `Brazil\HasAddress` declares the columns it reads (`@property` on the trait). Static analysis read
  every one of them as an undefined property on the USING model, so the report landed where the trait
  is used instead of where it is written — 75 errors across five models in chapaja, all of them this.
  rnc has no model using it, so nothing here changed but the contract becoming explicit.

## 1.3.1 — 2026-09-17

### Documented

- The address block's write path: `AddressData` is for reading, and spreading its `toArray()` into an update nulls `lat`/`lng` the request never sent (from chapaja-084).
- `Brazil\HasAddress` carries `// @phpstan-ignore trait.unused` on its declaration, with the reason in the docblock — no bootstrap model uses it yet.

## 1.3.0 — 2026-09-16

### Added

- The Brazilian address block (from chapaja): `Brazil\AddressColumns` registers the
  `addressColumns()` / `localityColumns()` Blueprint macros, `Brazil\AddressData` /
  `Brazil\LocalityData` mirror them, and the `Brazil\HasAddress` trait reads a row as either DTO.
  Chapaja kept them in `app/DTOs` and `app/Concerns` with the macros inline in the provider; here
  they sit in the removable Brazil directory and the provider only calls `register()`.
- `AddressBlockTest` — the macros, their nullability, the trait and the DTOs, on tables the test
  creates, since no bootstrap table stores an address.

### Fixed

- This file's title read `Changelog — ---`.

## 1.2.1 — 2026-09-15

### Changed

- `ApiResponse::paginated` and `paginatedCollection` declare `: JsonResponse`.

## 1.2.0 — 2026-09-14

### Added
- `Pagination` — clamps `per_page` and `page` at the request edge (from chapaja).
- `ApiResponse::paginated` / `paginatedCollection` — a paginator inside the usual `data` envelope.
- `Money` — integer cents and basis-point shares.
- `Brazil\Cpf`, `Brazil\Cnpj`, `Brazil\PhoneNumber`, `Brazil\Real` — Brazil's rules in one removable directory.

### Fixed
- `RULES.md` documented an envelope (`success` / `message` / `errors`) that `ApiResponse` never emitted. It now describes `data` / `error` / `status_code`.

## 1.1.0 — 2026-06-01

### Added
- Unit tests: `ApiResponseTest` covering all response factory methods.

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
