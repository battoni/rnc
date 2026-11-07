# Changelog — MAddEditUserForm

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.4.1 — 2026-09-17

### Fixed

- On a phone, the dialog's gap filler (`absolute bottom-0 w-full`) had no `left`, so it sat at its static position 20px in and ended 20px past the sheet; on a phone the page grew to 410px, the browser zoomed out, and the dialog covered 370 of 390px with a strip of page on the right. It takes `left-0`. Covered by `e2e/theme/dialog-mask.spec.ts` ("the dialog on a touch phone") and `src/test/dialogGapFiller.spec.ts`.

## 1.4.0 — 2026-09-17

### Added

- `MAddEditUserForm.spec.ts` (chapaja-220, from chapaja 708d292 in spirit): a 422 is shown inline and raises no toast, anything else raises the error toast, and the username check branches on 409 versus a refused check. Each case was seen failing against the defect it guards.

### Fixed

- A username check that failed for any reason other than 409 (a 403 since the check route asks for `users.create`/`users.update`, or a network error) read as "name is free". It now shows `users.form.usernameCheckUnavailable` under the field, in `text-muted`, and leaves the save open — the API still refuses a taken name with a 422 on submit.
- The taken-name branch compares with `HTTP_STATUS.CONFLICT`, not the literal `409` (ltv-135).
- Footer buttons `gap-2` → `gap-3`: 8px let the grown touch areas of Cancel and Save overlap (VIS-15).
- The permissions `MultiSelect` takes `inputId`, not `id`, so its `<label for>` reaches the combobox (lifeshifter-471).

## 1.3.0 — 2026-09-16

### Changed

- Branches on `HTTP_STATUS` instead of a bare status number. The same code appears in eight files, and a reader had to rebuild what the API means by it every time (from largatudoevai).

## 1.2.0 — 2026-09-14

### Fixed

- Nome, Sobrenome and Usuário focused nothing from their labels: `inputId` is not an `InputText` prop and fell through as a stray attribute. They take `id` (from lifeshifter).
- Both password fields pass `autocomplete` through `:inputProps` instead of to the wrapper `<div>` (from lifeshifter).

### Added

- `users.form.roleUnknown`: a role the catalogue does not know is no longer reported as "role is required" over a filled-in field (from lifeshifter).

## 1.1.0 — 2026-09-14

### Changed

- Editing your own account disables the status field and says why (from chapaja).
- A 422 shows the API's translated reason inline and no longer also raises the generic error toast (from chapaja).

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
