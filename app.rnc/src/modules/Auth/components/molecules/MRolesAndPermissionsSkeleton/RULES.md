---
version: 1.0.0
origin: pendulum
based-on: 1.0.0
---

# RULES — MRolesAndPermissionsSkeleton

> For AI agents. Last updated: 2026-05-22.

## Purpose

Loading skeleton that mirrors the layout of the RolesAndPermissions view: header row, tab strip, toolbar row, and a 3-column grid of permission panel skeletons.

## Intentional Decisions

- **No script block**: pure template, no props. The skeleton structure is fixed to match the view's real layout.
- **6 skeleton panels** in a `grid-cols-1 md:grid-cols-3` grid: mirrors the production grid breakpoints and a typical roles-and-permissions load.
- **Widths and heights are fixed to match real UI elements**: do not adjust dimensions without also checking the real layout in `RolesAndPermissionsView`.

## Do Not

- **Do not add props for dynamic skeleton count**: the skeleton is a static approximation of the real page layout.
