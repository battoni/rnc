---
version: 1.0.0
origin: rnc
---
# RULES — Tracking Module

> For AI agents. rnc-1 F2, 07/11/2026.

## Domain

The funnel's raw material (R7, R24): anonymous `visitors` (a UUID v4 the browser keeps, the source
of the first visit) and their `events` (`TrackingEvent`, table `events`) — page views, CTA clicks
and, from F3, the diagnostic steps and the booking. F7's metrics read them.

## The contract (plan AM2)

`POST /api/public/events` takes `{visitor, type, path, sector?, source, medium, lead_token?}` and
nothing else (`RecordEventRequest`; the DTO reads `validated()`):

- `visitor` a UUID v4; `type` ∈ `EventType` (`visit, cta, diag.start, diag.contact, diag.result,
  booking`); `path` ≤ 255; `sector` one of the current firm's `sector_pages` slugs; `source` ∈
  `TrafficSource` (`organico, pago, parceiro, direto`); `medium` ≤ 60.
- **`lead_id` never comes from the client** — the service resolves it from `lead_token`, and an
  unknown token is ignored. **`firm_id`** is the request's (`ResolveCurrentFirm`), never the body's.
- **The raw referrer is not stored**: the site derives the source and sends only that word.
- **Throttled 60 a minute per address** (`throttle:60,1` in `routes/api.local.php`).
- **The body may be `text/plain`** — the site sends by `navigator.sendBeacon`, which needs no CORS
  preflight that way; `prepareForValidation` reads a plain body that is JSON.
- Answers `201` with an empty body: a public endpoint echoes nothing.

## Intentional Decisions

- **One visitor row per (firm, uuid)**, created on its first event with that event's source and
  medium (first touch); later events update `last_seen_at`. A race on the first event meets the
  unique index and reads the winner back.
- **`events.lead_id` has no foreign key** (AM2, database/RULES.md "A log row's reference"): an event
  never blocks the delete of what it logged. F3 may index-join it to `leads`.
- **Tables read from other modules:** `sector_pages.slug` (Content), for the `sector` rule; `leads`
  (Lead), through `LeadService::findLeadByToken`, to turn a `lead_token` into `lead_id` (F3).

## Known Gaps

- **A bot can inflate the funnel** (AM2). The throttle stops a loop from one address, not a crowd;
  there is no bot filter, no CAPTCHA and no deduplication of page views.
_Retired 07/11/2026 (F3):_ "`lead_token` resolves to nothing yet" — F3 resolves it: a live token of
the current firm gives the event its `lead_id`; an unknown, expired or other firm's token records
the event anonymous.
- **No retention rule.** Events are kept indefinitely until LGPD retention is decided.
