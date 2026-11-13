---
version: 1.0.0
origin: rnc
based-on: 1.0.0
---

# RULES — Assistant Module

> For AI agents. rnc-3 F5, 07/11/2026.

The Reforma assistant's three screens under Equipe (rnc-3 R10–R15), over the API Assistant module
(its `RULES.md`): "Assistente da Reforma" (`/equipe/assistente` + `/team/assistant`), "Perguntas para
especialista" (`/equipe/assistente/perguntas` + `/team/assistant/questions`) and "Base da Reforma"
(`/equipe/base-da-reforma` + `/team/knowledge`). Every route is `assistant.ask`; who may read the
inbox and the base (a specialist, `assistant.manage`) is the API's answer, and a 403 shows who does.

## The assistant never writes an answer (W9, C4)

- **An answer is one of Franco's notes or nothing.** `MAnswerCard` prints the note's question, text,
  "Regra: <rule>", "Fonte: <source>, lida em <as_of>" and "revisado por <name> em <date>" — every
  answer says where it came from, who vouched for it and when (A14).
- **With no served note the screen says the fixed sentence** — "Não há nota revisada pela equipe para
  essa pergunta, então não vou arriscar uma resposta." — from its locale, never from the API, and
  offers the hand-off (`MHandoffCard`): a picker of the firm's active specialists the ask answered,
  the first by name chosen (W11). The card says who has the question once handed.
- **A chip asks by note id** (`note_id`), with its question as the words to log.
- **The transcript is the screen's.** Nothing of it is stored here; the API logs every ask (R15).
  A failed ask removes its turn and says so in a toast.

## Dates

- Dates are written dd/mm/yyyy in both languages (as the channel's screen does): an instant
  (`reviewed_at`, `answered_at`) as its São Paulo day, a plain day (`as_of`, the bell's
  `expires_on`) without a time-zone shift (`helpers.ts`).

## Who sees what

- **The way to the inbox** (a link in the assistant's header) and **"Base da Reforma" in the rail**
  are shown to a specialist (`user.is_specialist`) or `assistant.manage` — the people the API lets
  read them. "Specialist" is a flag, not a key, so the rail entry is added from the signed-in user
  (`navItems.local.ts`), not by `permission` alone.
- **Only the assigned specialist gets an answer box**; Reinaldo reads every question and sees
  "Aguardando <name>" on the open ones. "Virar nota" is offered on answered questions not yet noted.
- **"Revisar" follows the API's `can_review`** (a specialist who did not write the last change);
  "Aposentar" (after a confirmation — there is no un-retire) and "Perguntas sem nota" are
  `assistant.manage`'s; the gaps are not even asked for without it.

## Intentional Decisions

- **The counter is the API's.** "N notas vencem em 7 dias" prints `expiring_count`; a note's "vence
  em N dias" chip shows from 7 days left (`NOTE_WARNING_DAYS`), off `expires_in_days`. The screen
  computes no day of its own.
- **Granular updates**: review, retire, answer and "Virar nota" replace the one row from the API's
  answer; a review refused for a retired note reloads the base.
- **The note form owns its call** (celer-07) and refuses the `StoreNoteRequest` limits first; keys are
  typed comma-separated, trimmed and de-duplicated. Editing a revisada note warns it goes back to
  rascunho (D7). A source address is linked only when it is http(s) (`safeUrl`), in a new tab.
- **Motion is sparing**: the panels and each turn rise once on arrival (`motion-enter`), the chips
  change colour on `motion-calm`; all collapse under reduced motion.

## Known Gaps

- **No link from a bell to its question or note** (Notification Known Gap).
- **No search or filter in the base or the inbox**: the seed has ten notes and two questions.
