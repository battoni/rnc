---
version: 1.1.1
origin: pendulum
based-on: 1.1.1
---

# RULES — OWalletTransactions

> For AI agents. Last updated: 2026-05-22.

## Purpose

A scrollable DataTable for financial transaction history, with tone-mapped status and type tags.

## Intentional Decisions

- **`WalletTransactionItem` is defined locally and not exported**: the interface lives in this file only. If other components need to reference it, move it to the module's `interfaces.ts` and re-export from there.
- **`celer-tag-soft-*` CSS classes** (`celer-tag-soft-danger`, `celer-tag-soft-success`, etc.) are global utility classes from `styles/components/` — not Tailwind utilities. Do not replace with `bg-*/text-*` color classes.
- **`columnBodyClass` / `columnHeaderClass` are shared across all columns**: all columns use the same padding, border, and font styles. If a single column needs different styling, pass `bodyClass`/`headerClass` directly on that `Column`.
- **`scrollHeight="flex"` + `max-h-96` on mobile**: the DataTable fills available height on md/lg but is capped at 24rem on mobile. Do not remove the `max-h-96 md:max-h-full lg:max-h-full` combo.
- **`statusTone` and `typeTone` are semantic, not raw colors**: the parent passes tone names (`'error'`, `'success'`, etc.), and this component maps them to CSS classes. Never pass raw color class names through props.

## Prop & Emit Contract

- `transactions`: array of `WalletTransactionItem`. All fields are required. `statusTone` drives the status tag appearance; `typeTone` drives the type tag appearance.

## Do Not

- **Do not add DataTable border/cell styles via Tailwind** — these are controlled globally in `primevue.css`.
- **Do not add sorting logic here** — `sortable` is set on columns but the DataTable uses PrimeVue's built-in client-side sort. No custom sort functions needed.
