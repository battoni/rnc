# Changelog — Notification Module

## 1.4.0 — 2026-11-14

### Changed

- The bell's team roles read `gestao` instead of `ceo` (rnc-4 F2c1, R14).

## 1.3.0 — 07/11/2026 (rnc-3 F5)

- `AssistantAnswerNotification` (`kind: assistant_answer`, `{question_id, question, answered_by}`): the specialist answered a handed-off question — to the asker alone (R14; `QuestionAnswerTest`). `AssistantNoteExpiringNotification` (`kind: assistant_note_expiring`, `{note_id, question, expires_on}`): a review the person made is served only until `expires_on` — to the reviewer alone, once per review (AM19; `NoteExpiryTest`). Both through `TeamNotifier::notifyUser`.

## 1.2.0 — 07/11/2026 (rnc-3 F4)

- `AssistantQuestionNotification` (`kind: assistant_question`): the Reforma assistant had no reviewed note and a member of the team handed the question to a specialist — `{question_id, question, asked_by}`, to that specialist alone through the new `TeamNotifier::notifyUser` (R13; tested by `QuestionHandoffTest`). The bell's sentence for this kind is F5's (`notifications.kinds.assistant_question`).

## 1.1.0 — 07/11/2026 (rnc-2 F2)

- `ReferralStatusNotification` (`kind: referral_status`): a partner accepted, declined or closed a referral — `{lead_id, referral_id, company, partner, status[, deal_value_cents]}`, to the team through `TeamNotifier` (R9; tested by `PortalReferralsTest`).

## 1.0.0 — 07/11/2026 (rnc-1 F4)

- `NewLeadNotification`, `MeetingBookedNotification`, `TeamNotifier`; the person's inbox routes.
