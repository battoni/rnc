# Changelog — Assistant Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-07

### Added

- rnc-3 F5 (R11, R14, AM19, A13, A28): `GET assistant/questions` (a specialist's own, every one with `assistant.manage`, else 403 `assistant.notWriter`) and `POST assistant/questions/{id}/answer` (assigned specialist only, else 404 `assistant.questionNotFound`; once, else 422 `assistant.answered`; rings the asker through `AssistantAnswerNotification`); "Virar nota" — `from_question` on `POST assistant/notes`, tied once through `assistant_questions.note_id` (422 `assistant.alreadyNoted`); the notes list's `expiring_count` and each note's `expires_in_days`; `NoteExpiryService` + `assistant:notify-expiring` (daily 07:00 São Paulo, every firm) ringing the reviewer at day 83 once per review (`assistant_notes.expiry_notified_at`, `AssistantNoteExpiringNotification`). `AssistantNotePolicy::manage`, `AnswerQuestionRequest`; `QuestionResource` gains `asked_by`, `answer`, `answered_at`, `note_id`. Tests: `QuestionAnswerTest.php` (A13 rest), `NoteExpiryTest.php` (A28), `Phase3AccessTest` (A25 F5 rows), route classes.

## 1.0.0 — 2026-11-07

### Added

- The Reforma assistant's API (rnc-3 F4, R10–R13, R15, R16, D7–D9, AM4, AM7, AM19): `assistant_notes`, `assistant_questions`, `assistant_asks` tables; `config/assistant.php` (`driver` = `curated`); `Contracts/ReformaAnswerer` bound from `AppServiceProvider::ASSISTANT_DRIVERS` (unknown driver fails at boot); `Drivers/CuratedNotesAnswerer`; pure `Helpers/QuestionNormalizer` and `Helpers/NoteMatcher` (the demo's scoring, ties by the newer review then the lower id); `NoteStatus` (rascunho → revisada ↔ rascunho, → aposentada terminal), `QuestionStatus`; `AssistantNote` (`VALID_DAYS = 90`, `scopeServed`, day 90 counted in São Paulo), `AssistantQuestion`, `AssistantAsk`; `AssistantNotePolicy` (write: `assistant.manage` or a specialist; review: a specialist who is not the last editor); `AssistantException` (403 `assistant.notReviewer` / `assistant.notWriter`, 422 `assistant.notSpecialist` / `assistant.retired`, each with a `code`); `NoteService`, `AskService`, `QuestionService` (hand-off + bell through `TeamNotifier::notifyUser`); requests, DTOs, resources (`AnswerResource` carries no text of its own); `AssistantController` (`POST assistant/ask`, `GET assistant/suggestions`, `POST assistant/questions`, `GET assistant/gaps`) and `NoteController` (`GET/POST assistant/notes`, `PATCH assistant/notes/{id}`, `POST assistant/notes/{id}/review`, `POST assistant/notes/{id}/retire`). Tests: `tests/Unit/Modules/Assistant/NoteMatcherTest.php` (A11), `tests/Feature/Assistant/AskTest.php` (A12), `NoteReviewTest.php` (A10, AM10, AM19), `QuestionHandoffTest.php` (A13 hand-off half), `AssistantGapsTest.php` (A14 Pest half), `AssistantDriverTest.php` (A15), `Phase3AccessTest` (A25 assistant rows).
