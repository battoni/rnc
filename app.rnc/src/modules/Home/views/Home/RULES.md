---
version: 1.2.0
origin: pendulum
based-on: 1.0.0
---

# RULES — HomeView

> For AI agents. Last updated: 2026-11-07 (rnc-2 F1).

## Purpose

The back office's landing (`/painel`, route `home`): a greeting and the workspaces the person's keys
open (rnc-1). A partner's login never stays here.

## Intentional Decisions

- **A partner is sent on to its portal** (rnc-2 R3, A4): when `usePortalLanding().isPortalOnly`
  (`portal.access` and no `home.read`) the view calls `router.replace` to the portal's route and
  renders nothing — no flash of the back office. The router falls every refused route back to
  `home`, so this is the one place that bounces a partner from all of them.

_Retired 07/11/2026 (rnc-2 F1):_ "Placeholder home view — intentionally minimal, do not add
features until the product home screen is designed". rnc-1 designed it (the greeting and the
workspace cards) and this file had not been told.

## Do Not

- **Do not gate the route itself** with `meta.permission`: refused navigations land here, and a key
  on it would loop (module `RULES.md`).
