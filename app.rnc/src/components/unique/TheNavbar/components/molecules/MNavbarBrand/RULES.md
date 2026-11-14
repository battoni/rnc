---
version: 1.0.0
origin: rnc
---

# RULES — MNavbarBrand

> For AI agents. rnc-4 F2b, 14/11/2026.

## Purpose

The back office's brand block at the top of the rail (`ONavbarDesktop`) and the drawer
(`ONavbarDrawer`): the host firm's tile (`ALogo variant="min"`), its logo text, and the product's
name (`platform.name`, R9) under them.

## Intentional Decisions

- **It reads `useFirmBrand` itself, with no props** (rnc-4 R10): the firm is the app's, not the
  navigation's, and both organisms stay presentational. `TheNavbar` passes nothing for it.
- **Nothing renders until a firm is known** — the tab's stored answer or the API's. `ALogo` falls
  back to a name the build carries when given an empty one; an empty block for one request is better
  than the wrong firm.
- `data-testid="navbar-firm-name"` and `navbar-platform-name` are what the specs and
  `e2e/brand/two-palettes.spec.ts` read.

## Do Not

- Do not take the firm's name from `VITE_*` or a constant: the same build serves every firm.
