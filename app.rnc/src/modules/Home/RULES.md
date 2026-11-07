---
version: 2.0.0
origin: pendulum
based-on: 2.0.0
---

# RULES — Home Module

> For AI agents. Last updated: 2026-09-09.

## Domain

The authenticated landing view, and any top-level view that belongs to no specific domain module.

## What belongs here

- Top-level authenticated views that don't belong to a specific domain module

## What does NOT belong here

- **A demo screen.** See below — this module used to carry one and it is the reason for the 2.0.0.
- User or auth features
- Anything with its own entity, API and more than one view — that is a module of its own

## Public Barrel

Exports only `views` (route configs). No shared components, stores, or services — all data in this module is currently local state.

## Current Views & Status

| View       | Status      | Notes                                  |
| ---------- | ----------- | -------------------------------------- |
| `HomeView` | Placeholder | No content yet; pending product design |

## A prototype view is not a bootstrap feature (CRITICAL)

`FinanceView` lived here for months: a wallet dashboard with seeded balances, its own routes, a
navigation entry, a tip anchor, a seeded tip, and a locale namespace. None of it was real — the
data was local state and no backend was ever coming. It was removed in 2.0.0, and every derived
project had been deleting it by hand.

**Do not add a screen here to demonstrate a component.** The place to show a component working is
the Showcase module, which exists for exactly that and costs a project nothing to delete as a
whole. A demo dressed as a feature acquires the things features have — a route, a nav item, an
anchor, translations, tests naming it as an ungated screen — and each one is a thread somebody has
to pull later, in every project that inherited it.

The wallet components themselves (`MWalletCard`, `OWalletTransactions`) survive in the Showcase,
which is where they always belonged. They still read `finance.transactions.*` from the locales;
that namespace is now theirs and no longer a module's.

## Intentional Decisions

- **No store**: Home views manage local state only. If persistent state is needed, add `store.ts` at that point.
- **No module alias configured yet**: Home is not yet exposed as `@HomeModule` — import route configs directly from the barrel if needed.

## Do Not

- **Do not add business features to `HomeView`** until the product home screen is designed.
- **Do not re-add a prototype dashboard here.** Build it in the project that needs it, not in the bootstrap every project inherits.
