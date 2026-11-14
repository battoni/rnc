# Changelog — Briefing Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.2.0 — 2026-11-14

- Briefing without the partner network (rnc-4 F3c, R19, A25): `referrals` may be null — no referral tile (the KPI row is three wide) and no SLA panel (the panel row is two wide); a null fee range already read "sem base na carteira".

## 1.1.0 — 2026-11-14

### Changed

- Back-office paths `/reinaldo/*` → `/gestao/*` and `/ceo/*` → `/management/*`, route names `ceo-*` → `management-*` (rnc-4 F2c1, R14). No redirect is left at the old paths (A13).

## 1.0.1 — 2026-11-07

### Fixed

- `MCountdown`'s first frame read "CBS em vigor desde 01/01/2027" until `onMounted` started the count; it starts in setup now (rnc-3 review S2). Tested by `MCountdown.spec.ts` ("on its first frame").
- The greeting and the date line were read once: a briefing open across 12:00 still said "Bom dia". `BriefingView` re-reads the clock every minute and clears it on unmount (review S3, W115). Tested by `BriefingView.view.integration.spec.ts` under fake timers.

## 1.0.0 — 2026-11-07

### Added

- "Briefing do dia" (rnc-3 F9, R26, A23): `BriefingView` at `/reinaldo/briefing` (`/ceo/briefing`, route `briefing`, `briefing.read`) — the greeting by the São Paulo hour, the date line, the four KPIs (new leads 7 days / 24 h, meetings in 5 business days / today, referrals awaiting contact by SLA, the open pipeline's annual range or "sem base na carteira"), "As 3 ações de hoje" (each kind's sentence from the API's numbers, a button to its `to`), `MCountdown` (to 01/01/2027 00:00 São Paulo, every second, dated caption and the 2028 note), "Próximas reuniões", "Indicações e SLA de 24h úteis" (5, "e mais N", `MSlaChip`) and "Pipeline por etapa"; loading, error and empty states; `GetBriefingService`; locale `briefing.*` and `navigation.briefing` (pt-BR, en); the rail child first under Reinaldo. Tested by `BriefingView.view.integration.spec.ts`, `MCountdown.spec.ts`, `routes.spec.ts`, Home's `views/Workspace/routes.spec.ts`, `navItems.local.spec.ts` and `e2e/briefing/briefing.spec.ts`.
