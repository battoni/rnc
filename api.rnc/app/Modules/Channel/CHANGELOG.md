# Changelog — Channel Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.1 — 2026-11-14

### Changed

- RULES: the route table names `gestao` where it said `ceo` (rnc-4 F2c1 renamed the role; entry added at verify, P2-C6).

## 1.0.0 — 2026-11-07

### Added

- The RNC channel (rnc-3 F3a, R6–R9, D5, D6, AM9, AM15): `channel_firms` and `channel_firm_events` tables; `config/channel.php` (350000 cents, 1500 bp); `ChannelStage` (forward-only graph, `ativo` terminal); `Helpers/ChannelShare` (half-up per firm, then summed); `ChannelFirm`, `ChannelFirmEvent`; `ChannelRepository` (the board's sums in one grouped query); `ChannelService` (board, create at contato, update, `advance` in a transaction writing the event); `ChannelException::invalidTransition` (422 `channel.invalidTransition`, `code: invalid_transition`); `StoreChannelFirmRequest`, `UpdateChannelFirmRequest`, `AdvanceChannelFirmRequest` (`{to}`); `ChannelFirmDTO`, `UpdateChannelFirmDTO`; `ChannelResource` and `ChannelFirmResource`, closed without `withPrices()` (no fee, share rate, notes or defaults); `ChannelController`: `GET channel` (`channel.read`), `POST channel/firms`, `PATCH channel/firms/{id}`, `POST channel/firms/{id}/advance` (`channel.manage`). Tests: `tests/Unit/Modules/Channel/ChannelShareTest.php` (A7), `ChannelStageTest.php` (A8), `tests/Feature/Channel/ChannelTest.php` (A7, A8, AM9, AM10, AM15), `Phase3AccessTest` (A25 channel rows).
