# Changelog — Portal Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-07

### Added

- The partner's inbox (rnc-2 F3, R5–R8, A8's Vitest half): `PortalInboxView` (replaces F1's `PortalHomeView` at `/parceiro`) with the KPIs Novos · Em andamento · Fechados · SLA estourado and "Próximas reuniões" from the list; `MPortalReferralCard` (profile, covered items, score, message, SLA, contact once accepted, the moves of its status); `MReferralMeetingForm` and `MReferralCloseForm` in the view's `MMainDialog`; `MSlaChip` (shared with Partner's routing panel and trail); `REFERRAL_STATUS`, `SLA_STATE`; services `GetPortalReferralsService`, `TransitionReferralService`. Tested by `PortalInboxView.view.integration.spec.ts`, `MPortalReferralCard.spec.ts`, `MReferralMeetingForm.spec.ts`, `MReferralCloseForm.spec.ts`, `MSlaChip.spec.ts` and `e2e/portal/inbox.spec.ts`.

## 1.0.0 — 2026-11-07

### Added

- The partner's portal (rnc-2 F1, R3, A4): routes `/parceiro` and `/partner` behind `portal.access`, `PortalHomeView` naming the partner and the firm (`GetPortalMeService`), `usePortalLanding` (Home's redirect), the rail's portal entry (`navItems.local.ts`, key and role `parceiro`). Tested by `routes.spec.ts`, `PortalHomeView.view.integration.spec.ts`, `navItems.local.spec.ts`, `HomeView.spec.ts` and `e2e/portal/access.spec.ts`.
