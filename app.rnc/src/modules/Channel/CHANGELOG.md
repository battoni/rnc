# Changelog — Channel Module

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-14

### Changed

- Back-office paths `/reinaldo/*` → `/gestao/*` and `/ceo/*` → `/management/*`, route names `ceo-*` → `management-*` (rnc-4 F2c1, R14). No redirect is left at the old paths (A13). `CHANNEL_ROUTE_NAMES.ceo` → `.management`.

## 1.0.0 — 2026-11-07

### Added

- The "Canal RNC" screen (rnc-3 F3b, R8, R9): `ChannelView` at `/operador/canal-rnc` and `/reinaldo/canal-rnc` (`channel.read`) — the hero of Franco's share a month and a year with what the others add once active, the count per stage, one row per firm with its stepper and share ("na ativação" until active), "Avançar para <etapa>" sending `{to: next_stage}` and reloading on `invalid_transition`; for `channel.manage` only the platform-fee lede, the fee per row, the add and the edit (AM15). `MAddEditChannelFirmForm` in `MMainDialog` (name, city, fee in reais, share in percent, notes; live share preview, AM9 bounds). Services `GetChannelService`, `AddChannelFirmService`, `UpdateChannelFirmService`, `AdvanceChannelFirmService`. Tests: `ChannelView.view.integration.spec.ts` (A9), `MAddEditChannelFirmForm.spec.ts`, `e2e/channel/channel.spec.ts`.
