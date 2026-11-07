---
version: 1.1.1
origin: pendulum
based-on: 1.1.1
---

# RULES — MConfirmPopup

> For AI agents. Last updated: 2026-09-17.

## Purpose

Wraps PrimeVue's `ConfirmPopup` with a custom container that surfaces Accept/Reject buttons and optional loading state.

## Intentional Decisions

- **`group` is required with no default**: every call site must pass a unique group string that matches the `useConfirm().require({ group: '...' })` call. Mismatched groups silently prevent the popup from opening.
- **`onAccept` / `onReject` emit the PrimeVue callbacks, not a boolean**: the consumer must call the received `acceptCallback` / `rejectCallback` to actually dismiss the popup. The component never calls them itself.
- **Default `confirmSeverity` is `'danger'`**: this component is intended for destructive actions. Override only when used for non-destructive confirmations.
- **Cancel and confirm sit `gap-3` (12px) apart** (celer-06). Both are `size="small"`, so each grows 6px under a finger; at `gap-2` the areas overlapped and confirm — the destructive, later sibling — won the strip. The preset's `confirmpopup.footer.gap` does not reach this footer, which the component draws itself through `#container`.
- **`loading` disables cancel and shows spinner on confirm**: this prevents double-submit while the parent handles the async operation.

## Prop & Emit Contract

- `group`: must exactly match the `group` passed to `useConfirm().require()` at the call site.
- `cancelLabel` / `confirmLabel`: override default i18n fallbacks (`common.actions.cancel` / `common.actions.delete`). Pass an already translated string: the component renders them as-is, with no internal `$t`, so a bare i18n key would show as the key.
- `popupClass`: forwarded to PrimeVue `ConfirmPopup`'s `:class` for positioning or width overrides.
- `@onAccept([acceptCallback])`: fires when the user clicks confirm. Consumer must call `acceptCallback()` to close the popup and proceed.
- `@onReject([rejectCallback])`: fires when the user clicks cancel. Consumer must call `rejectCallback()` to close the popup.

## Do Not

- **Do not call `acceptCallback` / `rejectCallback` inside this component** — that responsibility belongs to the consumer.
- **Do not use this for global/page-level confirms** — prefer `useConfirm` + a dedicated group per feature to avoid cross-popup interference.
