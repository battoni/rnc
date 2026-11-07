---
version: 1.0.0
origin: rnc
based-on: 1.0.0
---

# RULES — Channel Module

> For AI agents. rnc-3 F3b, 07/11/2026.

The "Canal RNC" screen (rnc-3 R8, R9): the Rede Nacional de Contabilidade firms Franco introduces
to the platform, read from `GET channel` (API Channel `RULES.md`). One view, two routes, both
`channel.read`: the operator's `/operador/canal-rnc` (+ `/operator/rnc`) and Reinaldo's
`/reinaldo/canal-rnc` (+ `/ceo/rnc`). An RNC firm is a pipeline record, not a tenant (D5).

## Who sees what (AM15)

- **Controls follow `channel.manage`, never the address.** With it: the platform-fee lede, the fee
  on each row, "Adicionar escritório", "Editar" and "Avançar para <etapa>". Without it (Reinaldo):
  his share per firm, the stage and the totals — no button at all on the screen.
- **Reinaldo's board has no `monthly_fee_cents`, `share_bp`, `notes` or `defaults` keys** (the API
  closes them, W39). The interfaces mark them optional and nothing reads them unguarded; the lede
  without `defaults` is the read-only one, which names no price and no "pela plataforma".
- **Neither screen names the platform's owners or how they split the rest** (R9). A9 asserts "Willy",
  "APW", "Battoni", "divisão" and "split" are absent in both languages.

## Intentional Decisions

- **Every answer is the whole board** (W41): a move, an add or an edit replaces `board` from the
  answer, so the hero and the counts follow with no second call.
- **An advance names its target** (C3): the button sends the row's `next_stage`, never a stage the
  screen computed. A 422 `code: invalid_transition` (stale screen, second click) or a 404
  `channel.notFound` reloads the board with an info toast — the screen branches on the code, not on
  the sentence. One advance at a time: the other buttons wait.
- **Money is the API's.** The hero and the rows print the cents they are given (`formatReal`); the
  only arithmetic here is the form's preview of Franco's share (`channelShareOf`, the API's half-up
  rule) while the operator types. Every amount says "valor a conferir — sem cobrança automática" (D6).
- **The form edits the share as a percent and the fee in reais**, and sends basis points and cents.
  The bounds (AM9: R$ 0,01–100.000,00, 0–50 %) are refused before the call; an API refusal on
  `monthly_fee_cents` or `share_bp` shows the same bounds sentence. A new firm starts at contato; the
  form has no stage field.
- **The notes are the operator's**: shown only in the form, never on the rows.
- **Motion is sparing**: the hero and the rows rise once on arrival (`motion-enter`, staggered); the
  hero figure rises again when it changes (keyed on the cents); the stepper's bars change colour on
  `motion-calm`. All collapse under reduced motion (theme tokens).

## Known Gaps

- **No event history on screen** — `channel_firm_events` is written, not read (API Known Gap).
- **No delete and no undo** of a firm or a move (API Known Gap).
