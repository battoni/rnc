# Changelog — Home Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 3.8.0 — 2026-11-14

### Changed

- `HomeView` keeps the firm-less operator in the back office while a view-as is on (rnc-4 F4d, R26): its back office is then the viewed firm's.

## 3.7.0 — 2026-11-14

### Changed

- `HomeView` (rnc-4 F4b, R24): the firm-less platform operator (`platform.is_operator`, no `firm`, holding `platform.firms`) is sent on to the platform console (`@PlatformModule`, `PLATFORM_ROUTE_NAMES.firms`) and sees nothing of the back office's landing — as a partner is sent to its portal. Tests: `HomeView.spec.ts`.

## 3.6.0 — 2026-11-14

- Management landing fallback (rnc-4 F3c, R17): `management-home` carries `meta.moduleFallback` (`/gestao/metricas`, `/management/metrics`), where `attachAuthGuard` sends it while the firm has `briefing` off.

## 3.5.0 — 2026-11-14

### Changed

- Back-office paths `/reinaldo/*` → `/gestao/*` and `/ceo/*` → `/management/*`, route names `ceo-*` → `management-*` (rnc-4 F2c1, R14). No redirect is left at the old paths (A13). The workspace key `ceo` → `management`.

## 3.4.0 — 2026-11-07

### Changed

- `/reinaldo` (`/ceo`, `ceo-home`) lands on "Briefing do dia" (`/reinaldo/briefing`) instead of the metrics (rnc-3 F9, R26, A23). Tested by `views/Workspace/routes.spec.ts` (new) and `e2e/briefing/briefing.spec.ts`.

## 3.3.0 — 2026-11-07

### Changed

- `HomeView` sends a partner's login (holds `portal.access`, not `home.read`) on to its portal with `router.replace` and renders nothing (rnc-2 F1, R3, A4): every refused back-office route falls back to `home`, so this bounces a partner from all of them. Reads `usePortalLanding` from `@PortalModule`.

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
