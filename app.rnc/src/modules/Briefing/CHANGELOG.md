# Changelog — Briefing Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.0 — 2026-11-07

### Added

- "Briefing do dia" (rnc-3 F9, R26, A23): `BriefingView` at `/reinaldo/briefing` (`/ceo/briefing`, route `briefing`, `briefing.read`) — the greeting by the São Paulo hour, the date line, the four KPIs (new leads 7 days / 24 h, meetings in 5 business days / today, referrals awaiting contact by SLA, the open pipeline's annual range or "sem base na carteira"), "As 3 ações de hoje" (each kind's sentence from the API's numbers, a button to its `to`), `MCountdown` (to 01/01/2027 00:00 São Paulo, every second, dated caption and the 2028 note), "Próximas reuniões", "Indicações e SLA de 24h úteis" (5, "e mais N", `MSlaChip`) and "Pipeline por etapa"; loading, error and empty states; `GetBriefingService`; locale `briefing.*` and `navigation.briefing` (pt-BR, en); the rail child first under Reinaldo. Tested by `BriefingView.view.integration.spec.ts`, `MCountdown.spec.ts`, `routes.spec.ts`, Home's `views/Workspace/routes.spec.ts`, `navItems.local.spec.ts` and `e2e/briefing/briefing.spec.ts`.
