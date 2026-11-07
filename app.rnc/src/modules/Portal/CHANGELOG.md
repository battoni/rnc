# Changelog — Portal Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.0 — 2026-11-07

### Added

- The partner's portal (rnc-2 F1, R3, A4): routes `/parceiro` and `/partner` behind `portal.access`, `PortalHomeView` naming the partner and the firm (`GetPortalMeService`), `usePortalLanding` (Home's redirect), the rail's portal entry (`navItems.local.ts`, key and role `parceiro`). Tested by `routes.spec.ts`, `PortalHomeView.view.integration.spec.ts`, `navItems.local.spec.ts`, `HomeView.spec.ts` and `e2e/portal/access.spec.ts`.
