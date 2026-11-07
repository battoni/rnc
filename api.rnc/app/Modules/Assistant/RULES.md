---
version: 1.0.0
origin: rnc
---
# RULES — Assistant Module

> For AI agents. rnc-3 F4, 07/11/2026.

## Domain

The Reforma assistant for Franco's team (rnc-3 R10–R16, D7–D9): a question is answered **only** by
one of Franco's own notes — reviewed by a specialist and in date — or handed to a specialist. It
never generates text (W9): there is no model, no embedding, no outward client in this module, and
`AssistantDriverTest` greps its PHP files for an HTTP client, curl and sockets.

## Tables

- **`assistant_notes`** (`AssistantNote`, `BelongsToFirm`): slug (unique per firm, from the question,
  `-2`… when taken; not editable), question ≤ 200, answer ≤ 2000, rule ≤ 200, source label ≤ 160 and
  URL ≤ 300, `as_of` (the day the source was read), `keywords_strong` / `keywords_weak` (JSON lists),
  `status` (`NoteStatus`), `reviewer_id`, `reviewed_at` (UTC), `last_edited_by`. People carry no
  foreign key (rnc-1 W25).
- **`assistant_questions`** (`AssistantQuestion`): asked by, specialist, question ≤ 300, status
  (`QuestionStatus`: aberta · respondida), answer and `answered_at` (F5).
- **`assistant_asks`** (`AssistantAsk`): every ask — user, question, `normalised`, `note_id` or
  null, `at` (UTC). Written once by `AskService`, never edited.

## Serving (R11, R12, D8, AM7)

- **Served = revisada and reviewed at most 90 days ago** (`AssistantNote::VALID_DAYS`,
  `scopeServed`). Days are São Paulo calendar days: a review is served to the last minute of day 90
  and not from the first of day 91 (W52). An expired review lists as `review_state: vencida` and
  answers nobody until a specialist reviews it again.
- **The matcher is the demo's** (`Helpers/NoteMatcher`, pure; `QuestionNormalizer` lower-cases,
  drops accents and non-alphanumerics): a key of ≥ 4 characters matches a word it starts, a shorter
  one the whole word; score 2 × strong + 1 × weak; at least 2; a tie goes to the newer review, then
  the lower id. Keys are normalised the same way as the question.
- **Only the current firm's served notes compete** — another firm's note never even scores (the
  keyword list is firm-scoped, and the winner is re-read through `served`).
- **A chip asks by id** (`note_id`): it answers only while that note is still served (W51).

## The contract (R16, D9)

- `Contracts/ReformaAnswerer` (`answer`, `answerById`) is bound from
  `AppServiceProvider::ASSISTANT_DRIVERS` by `config('assistant.driver')`; the only driver is
  `curated` (`Drivers/CuratedNotesAnswerer`). An unknown name throws at boot (W60). A new driver must
  still answer with a served note or null — it never writes text.

## Answers (R12, R13, W50)

- `POST assistant/ask` → `{question, note, specialists}`. A served note comes as `AnswerNoteResource`
  (text, rule, source, `as_of`, reviewer, `reviewed_at`). With no note, `note: null` and the firm's
  active specialists by name — the hand-off's picker, default first — and **no sentence of the API's
  own**: the fixed "Não há nota revisada…" is the SPA's (F5).
- Every ask is logged (R15), answered or not.

## Knowledge base (R10, R11, AM4, W10)

| Route | Key | Plus |
| --- | --- | --- |
| `GET assistant/notes` | `assistant.ask` | writer (policy) |
| `POST assistant/notes` | `assistant.ask` | writer — 201, rascunho |
| `PATCH assistant/notes/{id}` | `assistant.ask` | writer |
| `POST assistant/notes/{id}/review` | `assistant.ask` | reviewer (policy) |
| `POST assistant/notes/{id}/retire` | `assistant.manage` | — |

- **Writer** = `assistant.manage` (Reinaldo, the operator) or `users.is_specialist`; anyone else is
  403 `assistant.notWriter`, `code: not_writer` (W54). **Reviewer** = a specialist who is not the
  note's `last_edited_by`; else 403 `assistant.notReviewer`, `code: not_reviewer`. Reinaldo writes
  and never reviews.
- **A content change sends a note back to rascunho and clears its review** (D7, W53); an edit that
  changes nothing keeps it reviewed and keeps its editor.
- **aposentada is terminal** (W55): edit and review are 422 `assistant.retired`, `code: retired`;
  retiring twice is a no-op.
- Another firm's note is 404 `assistant.notFound`, never 403.

## Hand-off (R13, W11, W56)

- `POST assistant/questions` `{question, specialist_id?}` (`assistant.ask`) → 201: one aberta
  question and one bell (`AssistantQuestionNotification`) on that specialist alone. No
  `specialist_id` → the first active specialist by name. Anyone who is not an active specialist of
  the firm — or no specialist at all — is 422 `assistant.notSpecialist`, `code: not_specialist`.

## Gaps (R15)

- `GET assistant/gaps` (`assistant.manage`): the last 30 days' asks without a note, one line per
  `normalised` text — `{normalised, question (one of its wordings), count, last_asked_at}` — most
  asked first, then the latest; top 50, one grouped query (plan P13; W59).

## Tables read from other modules

- `users` (Auth): the firm's active specialists (`is_specialist`, `status`, `firm_id`), and names of
  reviewers and editors; permission keys through `PermissionService::forUser` (Auth).
- `notifications` is written through `TeamNotifier` (Notification), never directly.

## Known Gaps

- **No answer "por significado"** (R16): a question worded away from a note's keys finds nothing and
  is handed off. A semantic driver would be a new entry in `ASSISTANT_DRIVERS`.
- **Answering a question, the inbox, "Virar nota", the expiry counter and the day-83 bell are F5's**
  (R14, AM19). Until then the bell's `assistant_question` entry has no sentence in the SPA.
- **No un-retire.** An aposentada note stays retired; write a new one.
