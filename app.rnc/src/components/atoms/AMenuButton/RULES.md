---
version: 1.0.0
origin: pendulum
based-on: 1.0.0
---

# RULES — AMenuButton

> For AI agents. Last updated: 2026-08-27.

## Purpose

The way back to the navigation: folds the desktop rail, opens the drawer below `lg`.

## Intentional Decisions

- **It is an atom rather than markup inside `ThePageHeader` because it is the ONLY way to reopen
  a collapsed rail**, and a page-header slot is optional. A view that rendered no header used to
  leave the navigation unreachable with the state persisted — clearing `localStorage` was the only
  way out. `TheLayout` renders one itself when the page supplies no header, so the escape hatch
  never depends on what the page chose to draw.
- **Desktop folds, mobile opens.** Wide screens have no drawer — `ONavbarDrawer` is `lg:hidden` —
  so asking for it there dims the page behind a panel that never paints.
- **Pinned, it shows at every width; unpinned, only in the tablet range**, where the rail does not
  fit and the drawer is the whole navigation.

## Prop & Emit Contract

None. It reads `useUiStore()`.

## Do Not

- **Do not make its visibility depend on the page header.** That is the bug this atom exists to
  prevent.
