---
version: 1.0.0
origin: rnc
---
# RULES — Channel Module

> For AI agents. rnc-3 F3a, 07/11/2026.

## Domain

Franco as a sales channel of the platform to the other firms of the Rede Nacional de Contabilidade
(rnc-3 R6–R9, `commercial.md` §1). An RNC firm is a **record of Franco's pipeline, not a tenant**
(D5): no login, no provisioning when it activates, no people's data — a name, a city, a stage, a
fee, a share and notes. Onboarding an RNC firm as a firm of its own is outside Phase 3 (Known Gap).

## Tables

- **`channel_firms`** (`ChannelFirm`, `BelongsToFirm`): name ≤ 120, city ≤ 80, `stage`
  (`ChannelStage`), `stage_at` (UTC, `UtcDateTime`), `monthly_fee_cents`, `share_bp`, notes ≤ 500.
  `firm_id` is not fillable.
- **`channel_firm_events`** (`ChannelFirmEvent`, `BelongsToFirm`): one row per stage move — who
  (`user_id`, no foreign key: rnc-1 W25), from, to, at (UTC). Written only by `ChannelService::advance`,
  never edited.

## Money (D6, plan P5)

- **Integer cents and basis points.** Defaults in `config/channel.php` (350000 = R$ 3.500/mês,
  1500 = 15 %), copied onto each firm when it is added — so a change of default moves no firm on the
  board. Bounds (AM9): fee 1–10.000.000 cents, share 0–5000 bp, whole numbers, else 422.
- **The share rounds half-up per firm, then sums** (`Helpers/ChannelShare.php`, pure):
  `intdiv(fee × bp + 5000, 10000)`. R$ 3.500,10 at 15 % is R$ 525,02, never 525,01. A total is
  always the sum of the rows it is shown over.
- **Nothing is billed.** Every amount is "valor a conferir — sem cobrança automática" (the screen's
  words, F3b).

## Stages (R7)

- `contato → demo → implantacao → ativo`, on the enum (`next()`, `allowedTransitions()`,
  `canTransitionTo()`, `isTerminal()`). One step forward only; `ativo` is terminal.
- **`POST channel/firms/{id}/advance` names its target** (`{to}`): the stage the button showed. A
  skip, a step back, staying put, a second click or a stale screen is 422 `channel.invalidTransition`
  with `code: invalid_transition` (`ChannelException`). The move and its event are one transaction on
  the row read `lockForUpdate`.
- **A new firm starts at contato**; a `stage` sent to the add or the edit is ignored (neither
  request validates it, neither DTO carries it).

## Endpoints

| Route | Key | Answers |
| --- | --- | --- |
| `GET channel` | `channel.read` (gestao, operador) | the board |
| `POST channel/firms` | `channel.manage` (operador) | 201, the board |
| `PATCH channel/firms/{id}` | `channel.manage` | the board |
| `POST channel/firms/{id}/advance` | `channel.manage` | the board |

- **Every answer is the whole board** (`ChannelResource`): `summary` — `monthly_share_cents`,
  `yearly_share_cents` (× 12), `pending_monthly_share_cents` (the others' shares once they
  activate), `counts` by stage — and `firms`, one `ChannelFirmResource` per firm by id: id, name,
  city, stage, `stage_at`, `next_stage`, `share_cents`. So the hero and the counts follow a move.
- **Two queries whatever the number of firms** (plan P13): the rows, and the sums grouped by stage,
  fee and share (`ChannelRepository::summarizeChannelFirms`) — each group's share rounded once and
  multiplied by its count, which equals rounding per firm.
- **Another firm's channel firm is 404 `channel.notFound`**, never 403.

## Who sees the price (AM15)

- **The resource is closed by default.** Without `withPrices()` no row carries `monthly_fee_cents`,
  `share_bp` or `notes`, and the board has no `defaults`. `ChannelController::index` opens it only
  for `channel.manage`; every write opens it, since only `channel.manage` reaches one.
- Reason: `commercial.md:41` — the platform path is undecided and must be settled before Reinaldo
  sees the platform's list price. Reinaldo reads his share per firm, the stage and the year total.
- Neither answer says anything about how the platform's owners split the rest.

## Intentional Decisions

- **The notes are the operator's** — closed to Reinaldo with the prices (rnc-3 W39).
- **The seed writes no events**: `ChannelSeeder` puts each firm where the demo has it, 5–90 days
  back (fixed per firm); the trail starts at the first real move.

## Tables read from other modules

- `users` (Auth) — only the signed-in person's id, written on the event; `permission` keys through
  `PermissionService::forUser` (Auth).

## Known Gaps

- **No RNC firm becomes a tenant** (D5): activating one provisions nothing. A second firm on the
  platform is outside Phase 3.
- **No delete and no undo.** A firm added by mistake stays; a move cannot be taken back. The demo
  has neither; add them when the screen asks.
- **No event history on the screen.** `channel_firm_events` is written and not read yet.
