---
version: 1.0.0
origin: rnc
---

# RULES — Notification Module

> For AI agents. rnc-1 F4, 07/11/2026.

The back office's bell (R17): `MNotificationBell` with the unread count, polled every 30 s
(`NOTIFICATION_POLL_MS`, D5), a dropdown with one sentence per notification and mark-as-read (one
or all), through `useNotificationStore`.

## Intentional Decisions

- **Mounted from `ThePageHeaderActions.local.vue`** (a LOCAL file `ThePageHeader` renders at the end
  of its row), shown to whoever holds `notifications.read` — no feature import in a synced file.
- **The API stores data, the SPA writes the sentence** (`notifications.kinds.<kind>`), meeting times
  in São Paulo (AM9).
- **Granular updates**: mark-as-read replaces the one entry from the API's answer.

## Known Gaps

- **Polling, not push** (D5). A notification does not link to the lead yet: the lead screen is F5's.
