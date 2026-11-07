---
version: 1.1.0
origin: rnc
---
# RULES — Pipeline Module

> For AI agents. rnc-1 F5, 07/11/2026.

## Domain

The team's side of a lead (R18–R20): the board, the lead detail with the diagnostic the visitor
never sees, the team's moves and notes, and the agenda week. It owns no table: it reads Lead's and
Booking's and writes through `LeadService`.

## The routes (`routes/api.local.php`)

| Route | Permission | What |
| --- | --- | --- |
| `GET leads?search=&stage=&brand=` | `leads.read` | one column per stage in board order, its count and its newest 60 cards; `brand` is `main` or a sub-brand slug of the firm (rnc-3 AM13), any other 422 `brands.notFound` |
| `GET leads/{id}` | `leads.read` | the lead detail: contact, profile, full diagnostic (detail + rule), consent, meeting, timeline, notes |
| `PATCH leads/{id}/stage` | `leads.update` | `{stage, reason?, via?}` → `{lead: card, event}` |
| `POST leads/{id}/notes` | `leads.update` | `{text}` → the timeline line (201) |
| `GET agenda?week=YYYY-MM-DD` | `agenda.read` | the Monday–Friday week holding the date (this week by default, the coming one on a weekend), meetings by day, the weeks around |

## Intentional Decisions

- **The team moves a lead to any other stage, backwards too (AM13).** The graph is on
  `LeadStage::canBeMovedByTeamTo` (anything but where it is: 422 `pipeline.sameStage`) and
  `requiresReason` (perdido: 422 `pipeline.lostReasonRequired` on `reason`, from the request and
  again in the service). The reason is kept on `leads.lost_reason` and on the line; it is cleared
  when the lead leaves perdido.
- **A move is one `etapa.alterada` line with `{from, to, via[, reason]}` and the user** —
  `via` is `MoveVia` (`botao`, `arrastar`, `ficha`). F7's funnel counts "cliente" from these lines
  (A11), so a move is never written any other way.
- **Notes are timeline lines** (`nota.adicionada`, `{text}`, the user), not a table: the detail
  answers them twice — in the timeline and filtered as `notes`. A note touches the lead, so its
  card rises on the board.
- **The detail sends the whole diagnostic** (`RulesEngine::diagnose` with `detail` and `rule`), only
  here, behind `leads.read`: the public result never does (R13).
- **Mutations answer what changed**: the move returns the card and the line, so the board and the
  detail update granularly.
- **The agenda shows every booked meeting**, perdido leads included with their stage: the slot is
  still taken in the availability, and hiding it would hide why.
- **The brand chip (rnc-3 R4)**: the card and the detail carry `brand {id, short_name}`, null for
  the main brand, loaded once per column (`with('brand')`), never per card. A deactivated brand is
  still a filter: its leads exist.
- **Another firm's lead is a 404** (`BelongsToFirm` on `leads` and `appointments`).

## Tables read from other modules

`leads`, `lead_events`, `lead_consents` (Lead, through `LeadRepository`); `appointments`,
`specialists` (Booking, through `AppointmentRepository`); `firm_brands` (Firm, through
`BrandService::brandIdForFilter` and the lead's `brand` relation); `users` (the names on the timeline and the
specialists).

## Known Gaps

- **A column shows at most 60 cards** (`PipelineService::COLUMN_LIMIT`); its count is the whole and
  the search reaches the rest. No pagination per column yet.
- **The visitantes are many and anonymous**: they stay on the board (a stage of the funnel) without a
  company; folding empty ones is open (Lead Known Gaps).
- **The contact links do not log a call or an e-mail on the timeline** — they open the phone, the
  mail client or WhatsApp; registering the contact is a later phase.
