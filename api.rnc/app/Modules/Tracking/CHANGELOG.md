# Changelog — Tracking Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-07

### Changed

- `TrackingService::record` resolves `lead_id` from `lead_token` through `LeadService` (rnc-1 F3, AM2); unknown, expired or other firm's tokens are ignored. Tests in `PublicEventsTest`.

## 1.0.0 — 2026-11-07

### Added

- `visitors` and `events` tables, `Visitor` and `TrackingEvent` models (firm-scoped), `EventType` and `TrafficSource` enums, `POST /api/public/events` (validated per AM2, throttled 60/min per address, beacon body accepted) (rnc-1 F2, R7). Test: `PublicEventsTest`.
