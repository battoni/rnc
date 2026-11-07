---
version: 1.1.0
origin: pendulum
based-on: 1.1.0
---

# RULES — MAddEditProfileForm

> For AI agents. Last updated: 2026-09-17.

## Purpose

Add/Edit form for a Role (called "profile" in this module). Handles both create and update modes; emits the saved role to the parent.

## Intentional Decisions

- **"Profile" = "Role"**: the component is named `MAddEditProfileForm` but manages Role entities (`Profile` type). This naming was chosen because the UI labels it "profile" to end users. Do not rename to "role form" without updating i18n keys too.
- **Submit button is outside the `<Form>` element**: it has `form="profile-form"` and `type="submit"` to submit the PrimeVue Form by ID. This is required because the sticky footer must sit outside the scrollable `<Form>` in `MMainDialog`. Do not move the button inside the Form.
- **`isEditMode` determined by `!!props.profile`**: create mode when `profile` is `null`/`undefined`; edit mode otherwise.
- **`permissionGroups` are mapped to options**: the `permissionGroupOptions` computed transforms raw `PermissionGroup[]` to `{ icon, label, value }` for the MultiSelect.
- **Yup validation keys are i18n strings**: validation messages are i18n keys passed raw to Yup; `getErrorMessage` resolves them with `t()`. The colon-split logic (`message.split(':')[1]`) handles Yup's `"key: message"` format.

- **A 422 is shown where it is about, with no toast.** `errors.name` (a taken name) goes under the name field through `nameRefusal`; any other refusal key goes to `AFormError`. Only a failure that is not a 422 raises the error toast. `nameRefusal` marks the input invalid with `true` or `undefined` — never `false`, which would override the resolver's own state.

## Prop & Emit Contract

- `permissionGroups`: all available `PermissionGroup[]` to populate the MultiSelect options.
- `profile`: the `Profile` to edit, or `null`/`undefined` for create mode. **Key the component on `profile?.id ?? 'new'`** at the call site to reset state when switching between roles.
- `@onClose`: fired when Cancel is clicked. Parent should close the dialog.
- `@onSuccess([role])`: fired after successful save with the returned `Profile`. Parent handles granular list update.

## Do Not

- **Do not call `GetRolesService` inside this component** — roles list is managed by the parent view.
- **Do not raise a toast for a 422** — the specific message is already on screen.
- **Do not put the footer buttons closer than `gap-3`.**
- **Do not add `form` attribute to the sticky footer buttons** — only the Submit button has `form="profile-form"`.
