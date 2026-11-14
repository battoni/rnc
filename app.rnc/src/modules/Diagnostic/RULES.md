---
version: 1.4.0
origin: rnc
---

# RULES — Diagnostic Module

> For AI agents. rnc-1 F3–F4, 07/11/2026.

The public Reforma diagnostic at `/diagnostico` (route `SITE_ROUTE_NAMES.diagnostic`, taken over
from the Site's F2 placeholder): step 1 by CNPJ or quiz, the contact right after it, the profile,
the faturamento, the locked result with the 2026–2033 simulator, the specialist slot picker, the
confirmation, and the "Relatório de Impacto" at `/relatorio/:token` (route `DIAGNOSTIC_ROUTE_NAMES.report`).

## Intentional Decisions

- **The chat is the `chat` module's** (rnc-4 F3c, R17): the mode toggle renders only while `public/firm`'s `modules` lists `chat` (`hasChat`) — hidden until the firm answers, and on the platform firm's root site, where a chat start is 422 `modules.disabled`.
- **`useDiagnostic` owns the flow**; the view only lays the steps out. Picking a path starts the
  lead (`POST public/diagnostics`) and keeps its token; each later step is saved when the visitor
  leaves it (`PATCH`), so a visitor who gives the contact and goes away is a lead parcial with its
  last step (R11). Nothing is sent before the visitor picks a path.
- **Each funnel step is tracked with the lead token** (`useTracking().track(type, sector, token)`)
  — the API resolves the lead (AM2). `visitContext()` gives the start the same visitor and source.
- **The CNPJ sequence ("Consultando Receita · Simples · CNAE") runs alongside the call**, 450 ms a
  line, and the answer shows when both are done. A 422 is "CNPJ não confere"; an unknown CNPJ or a
  failed call is the friendly fallback to the quiz — never an error page (D3).
- **The consent text is the API's** (`PublicLeadResource.consent.text`), so the words shown are the
  words stored. The checkbox starts unticked (AM12).
- **The result never has a detail** — the API does not send it; each item says it is unlocked in
  the meeting (R13). Every R$ figure is labelled "estimativa".
- **The simulator is HTML bars, not SVG**: an SVG scaled to a 390 px column shrinks its labels under
  12 px. Each year is a stacked column scaled to 32 pp.
- **Quiz answers are `MChoiceGroup`s** — radio groups drawn as 44 px buttons with
  `data-testid="{name}-{value}"`, easier to tap than a select and testable without an overlay.
- **A label wrapping a checkbox reads as a sentence** (`franco.css`, `label:has(.p-checkbox)`): the
  base theme draws every `label` as a mono, upper-case caption — unlayered, so a utility class
  cannot outvote it — and the consent read in capitals is a wall.
- **Route meta is `isOpen`** like every site route (W4), the report included (AM15).
- **A step change is an `out-in` cross-fade keyed on `step` AND `path`** (`step-fade`, in
  `styles/components/motion.css`), so picking "CNPJ" or "responder" crosses over too. The entrances
  inside a step are mount-time CSS (`motion-enter`, `motion-wipe-x`), never `v-reveal`: a step is on
  screen when it arrives. The simulator's columns wipe in sideways because their height is the rate
  (theme RULES, Motion: never animate the axis that carries a datum).
- **The slot picker is `MSlotPicker` + `useBooking`** (F4): the indicated specialist chosen first,
  the first day with a free slot, the e-mail asked only when the contact had none (AM5, `hasEmail`
  from the contact step). A `slot_taken`/`slot_unavailable` refusal reads the agenda again.
- **Meeting times are always São Paulo's** (`useMeetingTime`, `MEETING_TIME_ZONE`, AM9) whatever the
  browser's zone; the API sends ISO-8601 with the offset.
- **The report prints**: `ThePublicLayout`'s header and footer and the print button are `print:hidden`.

- **The chat is a second face of the same diagnostic (rnc-2 R12–R14, D11)**: `useChatReceptionist`
  writes each answer where the form's field writes it and saves through the form's own step, so it
  sends the form's PATCHes (A10's proof, `useChatReceptionist.spec`, AM8). The view holds both
  composables, so a switch either way keeps the answers and the transcript; the contact being typed
  is `useDiagnostic.contact`, shared by both. No LLM: `ReceptionistScript` is pure and never
  answers anything itself — text that matches no option, or two, is "Não entendi…".
- **The channel is the surface on screen when the lead starts** (`selectChannel`, a no-op once the
  token exists); the API keeps the lead's from then on, whatever the visitor switches to.
- **Typed text is read in every UI language** (`ChatContext.labels`): a visitor on the English site
  may still type "saúde". Accents, case and punctuation never matter; the longest option name the
  text holds wins, a tie is not an answer, and "não sei"/"talvez" is never read as a "não".
- **The chat's motion** is `motion-bubble` (each line rises on `calm`, the question a stagger after
  the answer, the replies one by one) and `motion-typing` (the reception typing while the API
  answers — the one idle loop, still under reduced motion); the log scrolls itself, smoothly unless
  the visitor asked for less motion.
- **An error belongs to the answer that produced it** (rnc-2 review): the chat shows its own error
  or the diagnostic's, and answering again clears both (`useDiagnostic.clearError`), so a refused
  contact is not still said once the turn has moved on. A lookup with no lead (its start failed) is
  `diagnostic.errors.unavailable` and starts the lead again — never "CNPJ inválido", in the form or
  the chat; the chat says the CNPJ is wrong only for a lookup that ran.
- **A company found that is not theirs** offers "Tentar outro CNPJ" and "Usar o questionário", as a
  CNPJ not found does, and forgets what the lookup filled (company, sector, regime).
- **A quick reply is logged as its key**, so the transcript re-translates on a language switch; a
  name the visitor typed (or the company's name offered as a reply) stays as written.
- **The cursor goes back to the box only at a free-text turn** (CNPJ, company, name, contact): a tap
  on a quick reply does not open a phone's keyboard.
- **The log is the live region** (`role="log"`, polite): the error line inside it carries no
  `role="alert"`, or a screen reader would say it twice.

## Known Gaps

- **No rescheduling by the visitor**: one meeting per lead (409 `already_booked`); changes are the team's.
- **The chat's transcript lives in the page**: a refresh starts the talk over, like the form (below).
- **The simulator stays in the form**: the chat's result links to it ("Ver o simulador") instead of
  drawing the 2026–2033 columns in a bubble.
- **A refresh starts over**: the token lives only in the page. The lead already saved stays with the
  team; resuming a diagnostic from a link is not built.
