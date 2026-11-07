# Changelog — Notification Module

## 1.1.0 — 07/11/2026 (rnc-2 F2)

- `ReferralStatusNotification` (`kind: referral_status`): a partner accepted, declined or closed a referral — `{lead_id, referral_id, company, partner, status[, deal_value_cents]}`, to the team through `TeamNotifier` (R9; tested by `PortalReferralsTest`).

## 1.0.0 — 07/11/2026 (rnc-1 F4)

- `NewLeadNotification`, `MeetingBookedNotification`, `TeamNotifier`; the person's inbox routes.
