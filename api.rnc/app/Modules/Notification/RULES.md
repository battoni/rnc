---
version: 1.1.0
origin: rnc
---
# RULES — Notification Module

> For AI agents. rnc-1 F4, 07/11/2026; rnc-2 F2 (`ReferralStatusNotification`).

## Domain

The back office's bell (R17): Laravel database notifications (`notifications`, AM7) for the team —
`NewLeadNotification` (a lead first left its contact), `MeetingBookedNotification` and, from rnc-2,
`ReferralStatusNotification` (a partner accepted, declined or closed a referral — Partner's portal
sends it after the move commits; contato, reunião and proposta do not ring) — and the
person's own inbox: `GET notifications`, `PATCH notifications/{id}/read`, `POST notifications/read-all`,
behind `notifications.read` (AM8).

## Intentional Decisions

- **`TeamNotifier` decides who hears the bell**: every active `equipe` and `ceo` member of the
  current firm. The operator is not paged by a firm's leads. No firm set → nobody.
- **A notification stores data, not sentences**: `kind` plus the fields; the SPA writes the pt-BR
  line from them, so wording changes never need a data migration.
- **Database channel only, not queued**: it is one insert per person in the request.
- **A notification's id is a UUID** (Laravel's key) — the one id on the wire that is not an integer.
  Somebody else's id is a 404 (the lookup is through the person's own relation).

## Tables read from other modules

`users` and `roles` (Auth), to find the team.

## Known Gaps

- **No pruning**: read notifications stay; a retention rule comes with the volume.
- **Polling every 30 s** (D5), no websockets.
