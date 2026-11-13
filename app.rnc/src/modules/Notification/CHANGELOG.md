# Changelog — Notification Module (app)

## 1.2.0 — 2026-11-07

- rnc-3 F5: the bell writes the Reforma assistant's three kinds — a question handed to a specialist, the specialist's answer, a review that runs out in 7 days — with their titles and icons; `BellNotification`'s lead fields become optional. Tests: `MNotificationBell.spec`.

## 1.1.0 — 2026-11-07

- The partner's moves in the bell (rnc-2 F3, R9, A8's Vitest half): `NOTIFICATION_KIND.REFERRAL_STATUS`, "Parceiro aceitou / recusou / fechou contrato" read by kind and status, the closing with its value. Tests: `MNotificationBell.spec`.

## 1.0.0 — 2026-11-07

- The bell (rnc-1 F4, R17): `MNotificationBell`, `useNotificationStore`, the three services, mounted by `ThePageHeaderActions.local.vue`. Tests: `MNotificationBell.spec`.
