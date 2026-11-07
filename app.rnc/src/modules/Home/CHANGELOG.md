# Changelog — Home Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 3.2.0 — 2026-11-07

### Changed

- `ceo-home` (`/reinaldo`, `/ceo`) redirects to Reinaldo's metrics (rnc-1 F7).

## 3.1.0 — 2026-11-07

### Changed

- `team-home` (`/equipe`, `/team`) redirects to the team's pipeline (rnc-1 F5).

## 3.0.0 — 2026-11-07

### Changed

- **BREAKING — `home` moved from `/` to `/painel`**: the root belongs to the public site (rnc-1 AM15). The view greets the person and lists the workspaces their keys open.

### Added

- `WORKSPACES`, `useWorkspaces`/`visibleWorkspaces` and `WorkspaceRoutes` (`/equipe`, `/reinaldo`, `/operador` and their `en` paths) with one `WorkspaceView` (rnc-1 F1, R3, A2).

## 2.0.0 — 2026-09-09

### Removed

- **BREAKING — `FinanceView`.** A wallet dashboard prototype on seeded local state, with no backend coming. It went with its routes (`finance-en` / `finance-pt-BR`), its navigation entry, the `finance.overview` tip anchor and the tip the seeder put on it, and the `finance.*` locale block apart from `finance.transactions.*`, which the surviving Showcase wallet components still read.
- A project that had already deleted it by hand inherits nothing new; one that still shows it loses the screen on sync. That is the intent.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
