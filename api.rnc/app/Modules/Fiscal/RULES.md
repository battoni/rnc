---
version: 1.0.0
origin: rnc
---
# RULES — Fiscal Module

> For AI agents. rnc-2 F6a, 07/11/2026 (the NF-e parser, the access key, the importer and the upload).

## Domain

The clients' NF-e (rnc-2 R15–R23): the XMLs Franco already receives from its clients, imported and
matched to the portfolio, read for the Reforma's readiness (the `IBSCBS` group of NT 2025.002), the
credit lost on Simples suppliers and the cash split payment holds back. It owns `nfe_imports` and
`nfe_documents`, and the raw files under `nfe/` on the private `local` disk. F6a builds the import of
one XML; the ZIP, the folder command, the batch list and the seed are F6b; the screens' maths F7–F9.

## The routes (`routes/api.local.php`)

| Route | Permission | What |
| --- | --- | --- |
| `POST fiscal/imports` | `fiscal.import` | multipart `file` (one `.xml`, ≤ 20 MB) → 201 `{id, source, user_id, files, stored, refused: [{file, code}], at}`; throttle 10/min, prefix `nfe-import` |

## Intentional Decisions

- **`POST fiscal/readiness/{id}/notice` and `POST fiscal/supplier-credit/{id}/review` (F7) write under
  `fiscal.read` on purpose (plan AM13).** They only add a line to the client's timeline (`nfe.aviso`,
  `fornecedores.revisao`) — no fiscal data changes — so the team that reads the screens may register
  them. `fiscal.import` is for what writes documents. Reviewers: do not reopen this.
- **One code path (R15)**: `NfeImporter::import(NfeDocumentSource, NfeImportSource, ?userId)` is what
  the upload, the folder command and the seeder call. Sources are chosen by the caller — no container
  binding on `NfeDocumentSource` (D5). Only local drivers exist: no SEFAZ, LLM or bank client.
- **XML safety (plan AM3, A14)**, in `NfeXmlParser`, in this order: over 1 MB → `too_large`, never
  read; any `<!DOCTYPE` (any case) → `not_nfe` by a STRING check before `loadXML`, so no entity —
  external (`file:///etc/hostname`) or nested (billion laughs) — can be declared; empty, not UTF-8 or
  holding a NUL byte → `not_xml` (a UTF-16 file hides its DOCTYPE from the byte search: with or
  without a BOM it is refused here). `loadXML` runs with `LIBXML_NONET` and never `LIBXML_NOENT`,
  `LIBXML_DTDLOAD` or `LIBXML_PARSEHUGE`; a parsed tree that still has a doctype is `not_nfe`. The
  refusal's message is its code, never a byte of the file.
- **What a document is**: `nfeProc` > `NFe` > `infNFe`, or a bare `NFe` > `infNFe`, tags read by local
  name (a file without the namespace reads the same). `ide/mod` 55 (an NFC-e 65 is `not_nfe`);
  `emit/CNPJ` required (a CPF emitter — a rural producer — is `not_nfe`, Known Gap); `ide/nNF`,
  `ide/serie`, `ide/dhEmi` (ISO-8601 with offset, stored UTC) and `total/ICMSTot/vNF` required and
  well-formed, else `not_nfe`; at least one `det`. `emit/CRT` outside 1–4 and a blank `ide/verProc` are
  kept as null, not refused. Recipient: `dest/CNPJ` → `cnpj`; a `dest/CPF` or no `dest` → `consumidor`
  with no number stored.
- **The access key (R16)** is `infNFe@Id` without its `NFe` prefix: 44 digits, the mod-11 check digit
  (`AccessKey`, weights 2–9 from the right, remainder 0 or 1 → 0), a valid embedded CNPJ, and that
  CNPJ and model must be the document's own `emit/CNPJ` and 55 — anything else is `invalid_key`.
- **Item checks (R16)**: each `det` counts under the FIRST check it fails — no `imposto/IBSCBS` group
  (`items_missing_group`, rejection 1115 once validated), then a blank `IBSCBS/CST` or
  `IBSCBS/cClassTrib` (`items_missing_codes`; the ICMS `CST` is never read for this), then the 2026
  test rates not shown (`items_without_test_values`: `gIBSCBS/gCBS/pCBS` = 0,9000 and
  `gIBSUF/pIBSUF` + `gIBSMun/pIBSMun` = 0,1000, compared as integers of ten-thousandths of a percent).
  An item passing all three is complete. `sample` holds up to five `nItem` per check, so the detail
  screen (F7) can name the invoice AND the item. Money and rates never pass through a float.
- **Matching (D6)**: by CNPJ against the CURRENT firm's clients (`ClientRepository::findClientIdsByCnpjs`,
  read only). The emitter's client gets a `saida`, the recipient's an `entrada`; a document between two
  clients is two rows (spec C1); a client that is both emitter and recipient gets one `saida`. No client
  → `client_unknown`; nothing is parked.
- **Duplicates (spec C1, plan AM4)**: unique per `(firm_id, access_key, client_id)`. The importer asks
  first and the unique index answers a race (`UniqueConstraintViolationException`, never a SQLSTATE).
  Each client row already held is one `duplicate` line for the file: the re-upload of a document
  between two clients stores nothing and lists the file twice, both `duplicate`.
- **Storage (D7, A15)**: the raw bytes as received, on the private `local` disk at
  `nfe/<firm id>/<client id>/<key>.xml` — the path built only from those two integers and the
  validated key, never from the uploaded name (plan AM3). The row and the file are written in one
  transaction: a file that cannot be written rolls the row back. No resource carries `path`; no route
  serves the files. The uploaded name is kept only as the batch's label (base name, ≤ 200 characters).
- **The batch (R17)**: one `nfe_imports` row per call — who (`user_id`, null for the command), how
  (`upload` · `folder`), `files` read, documents `stored`, `refused: [{file, code}]` in file order,
  `at` UTC. One bad file never stops the others. Codes: `not_xml`, `not_nfe`, `invalid_key`,
  `client_unknown`, `duplicate`, `too_large` (`NfeRefusal`).
- **Upload ceilings (plan Defaults)**: the request takes one `.xml` of up to 20 MB (422 past it, before
  any read); the source refuses an XML over 1 MB `too_large` from its size alone.

## Tables read from other modules

`clients` (Portfolio — the CNPJ → id of the firm's clients, through `ClientRepository`). `firms`
(through `CurrentFirm`: the import runs inside one; a run without one is a `LogicException`).

## Known Gaps

- **The signature is not verified (D7)**: an altered XML is believed. A forged file can only add rows
  to the importing firm's own clients.
- **No SEFAZ distribution (D5)**: NFeDistribuicaoDFe needs each client's A1 certificate and a live
  endpoint; no driver is written.
- **A CPF emitter is refused `not_nfe`**: a rural producer's NF-e to a client (an `entrada`) is lost
  until `emitter_cnpj` learns to hold a CPF.
- **The tag paths are `facts.md` §5's general knowledge, marked to confirm** against the current NT
  2025.002 layout before a client sees a figure; the parser reads defensively, so a moved tag shows as
  an issue, not a crash.
- **The `local` disk is `serve: true`** (the Laravel default, pendulum's config): `storage/{path}`
  answers a SIGNED temporary URL. Nothing in the app mints one for `nfe/`, and an unsigned request is
  refused (A15's test). Setting `serve` to false belongs to `.config-rules`.
- **PHP's own upload ceiling**: `upload_max_filesize` / `post_max_size` must be ≥ 20 MB on the server,
  or a large file fails as a 422 `file` before Laravel sees it (F6b's ZIPs).
