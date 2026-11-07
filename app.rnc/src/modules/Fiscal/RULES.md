---
version: 1.0.0
origin: rnc
based-on: 1.0.0
---

# RULES — Fiscal Module

> For AI agents. rnc-2 F8, 07/11/2026 (the readiness and supplier-credit screens, the XML import).

## Domain

The fiscal screens over the NF-e XMLs (rnc-2 R15–R22): the team's "Prontidão da NF-e"
(`/equipe/nfe`, route `nfe-readiness`, `/team/invoices` its `en` twin) and "Crédito de fornecedores"
(`/equipe/fornecedores`, `supplier-credit`, `/team/suppliers`), both behind `fiscal.read` in the
Equipe rail group; Reinaldo's "Notas fiscais" (`/reinaldo/notas`, `nfe-imports`, `/ceo/invoices`)
behind `fiscal.import` in his group. The API is the `Fiscal` module (`GET fiscal/readiness`,
`GET fiscal/readiness/{id}`, `POST …/notice`, `GET fiscal/supplier-credit`, `POST …/review`,
`GET/POST fiscal/imports`) and Portfolio's `GET clients/{id}/events`. The split-payment figure (R23)
has no screen here: the Radar (rnc-2 F10) shows it.

## Intentional Decisions

- **The API counts, the screen formats.** Every figure — the readiness %, the at-risk counts, the
  status, the hero, the supplier shares and the lost-credit range — comes computed (F7). Money is
  integer cents through `formatReal`; percentages and counts through `useFiscalFormat` in the
  screen's language. Never re-derive a status or a range here.
- **Ranges, labelled estimates.** The credit lost a year is always `[low, high]`, shown as
  "R$ x – R$ y" with an "estimativa" tag; the KPI headline uses the short form ("R$ 1,2 mi – R$ 1,3
  mi", the full range in its `title`) so it fits a card at 390 px. Readiness counts are facts read
  from the XMLs, not estimates.
- **The readiness hero is over every listed client whatever the filter** (state W76): the status
  buttons carry their counts and stay true while the list narrows. A filter asks the API again; the
  open client stays open only while it is still listed. A client with no imported sales reads
  "Sem notas importadas" and "—", never 0 %.
- **The notice text is written here** (W84): `MClientNoticeDialog` composes the demo's e-mail from
  the client's counts in locales — the issued invoices (W5), the %, the 1115 risk (plural by count),
  the emitting system or "sistema emissor" when none says —, and asks a `sem_notas` client for its
  XMLs instead. "Copiar texto" copies exactly the paragraphs on screen, blank-line separated.
  Nothing is sent (D12): the flag above the text says so.
- **One line per action, then the button gives way.** "Registrar aviso" and "Propor revisão" each
  POST once; the API answers the timeline line, which the screen prepends (`useClientEvents`) or
  marks on the row ("Revisão proposta", the KPI + 1) — no refetch (celer-07). A repeat is the
  API's to accept (W78); the screens do not offer one.
- **"Propor revisão" is disabled at 0 % Simples** with "Sem fornecedor do Simples" and an
  `aria-describedby` hint; a refusal the API still names (`no_simples_purchases`) is read by its
  `code` (`FISCAL_ERROR_KEYS`) and said on the row.
- **The timeline is shared** (`MClientTimeline`): the readiness detail loads it on open, a proposed
  supplier row opens it on demand. The sentence per type is written here from `data`; an unknown
  type is named, not hidden. A late answer for another client is dropped (`useClientEvents`).
- **The upload refuses early what the API would refuse whole**: no file, another extension, over
  20 MB — no request. A request it refuses is named by status (422 the file, 429 the 10-a-minute
  throttle, else a failure), never by its sentence. A batch with refusals is a success: it is shown
  expanded as "Último envio" and prepended to the list; listed batches keep refusals folded, five at
  a time before "Ver todas". A refusal reads its reason in words, never the bare code.
- **State words sit in their own tint.** The state palettes do not flip with the scheme, so a
  `text-danger-700` on a panel read 2.85:1 in dark (the E2E contrast test caught the hero, the
  range and the refusals). A state word is a chip (`bg-danger-50 text-danger-800`, as `MSlaChip`);
  a figure on a panel is `text-heading`. Pale brand tints carry `text-primary-950`
  (`brandTintText.spec`).
- **Motion, sparingly**: `motion-enter` on the cards and new lines, `motion-wipe-y` on the bars
  (they thicken; their width is the datum), `v-count-up` on the hero's one number. All of it is off
  under reduced motion (`motion.css`).
- **Sectors and regimes reuse Repricing's enums** (`CLIENT_SECTOR`, `CLIENT_REGIME` — the API's
  values) with this module's own labels under `fiscal.*`, as Repricing did with Diagnostic's.

## Known Gaps

- **A tiny Simples share that rounds to 0,0 %** disables "Propor revisão" though the API would accept
  it (it refuses only when nothing came from Simples) — the same rounding that leaves it out of
  "N de M" (api Fiscal RULES).
- **The readiness list is not paginated**: the API answers every Presumido/Real client (≈ 270 on the
  seed, 229 of them without notes); the table scrolls inside itself with a sticky header.
- **The screens do not poll**: a batch imported elsewhere shows on the next load.
