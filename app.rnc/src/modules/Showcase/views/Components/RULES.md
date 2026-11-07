---
version: 1.3.2
origin: pendulum
based-on: 1.3.2
---

# RULES — ComponentsView

> For AI agents. Last updated: 2026-09-17.

## Purpose

Living reference for all shared UI primitives in the design system: typography, buttons, form inputs, cards, tables, dialogs, toasts, messages, tags, and badges.

## Intentional Decisions

- **Only visible with `design.read`**: the route declares `meta.permission` and the global auth guard enforces it. Not a role check — a role cannot be granted from the permissions tab.
- **No API calls**: all data is static. Data never comes from the backend.
- **Every word a person reads is an i18n key** under `showcase.components.*`, in `en` and
  `pt-BR`. This replaces "`title` passed as a raw string — internal tooling, so hardcoded labels
  are acceptable": the view had 0 `$t()` calls, so a pt-BR session saw an English gallery of a
  design system that ships in two languages, and a layout checked only in English has not been
  checked (`06-design-system`). Literal on purpose: brand and people names, SKUs, dates, amounts,
  and the PrimeVue component names used as field labels (`InputText`, `Select`…) — those are
  identifiers, not copy.
- **Sample data carries keys, and computeds translate it.** The `OList*` family prints what it is
  given and looks badge icons up by the printed value, so an icon map is keyed by the TRANSLATED
  word (`translateKeys`). A map keyed by the English word loses every icon in pt-BR.
- **The Brazil address block has two sections** (`MAddressFields`, `MLocalityFields` with
  `showRegion`), each bound to a local `ref`. The CEP lookup is the one live call on this screen:
  it goes to ViaCEP, a public utility, never to our API — the "no API calls" line below is about
  the backend.
- **Each section is a `<section>` with a heading**: sections mirror design system categories. Add a new section when a new shared component is added to `src/components/`.

## Do Not

- **Do not remove this view** — it is the reference for AI agents and developers to see the real rendered output of shared components.
- **Do not add business logic** — purely a visual test bed.
