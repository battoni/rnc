---
version: 1.0.0
origin: rnc
---

# RULES — Diagnostic Module

> For AI agents. rnc-1 F3, 07/11/2026.

The public Reforma diagnostic at `/diagnostico` (route `SITE_ROUTE_NAMES.diagnostic`, taken over
from the Site's F2 placeholder): step 1 by CNPJ or quiz, the contact right after it, the profile,
the faturamento, the locked result with the 2026–2033 simulator, and the specialist CTA.

## Intentional Decisions

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
- **Route meta is `isOpen`** like every site route (W4).

## Known Gaps

- **"Agendar com um especialista" opens a holding step** (`DIAGNOSTIC_STEP.BOOKING`) that hands over
  Franco's phone and mailbox: F4 replaces it with the slot picker (W20).
- **A refresh starts over**: the token lives only in the page. The lead already saved stays with the
  team; resuming a diagnostic from a link is not built.
