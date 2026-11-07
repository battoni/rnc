---
version: 1.1.0
origin: rnc
based-on: 1.1.0
---
# RULES — Fiscal Module

> For AI agents. rnc-2 F6a–F6b, 07/11/2026 (the NF-e parser, the access key, the importer, the upload
> of an XML or a ZIP, the folder command, the batch list and the seed).

## Domain

The clients' NF-e (rnc-2 R15–R23): the XMLs Franco already receives from its clients, imported and
matched to the portfolio, read for the Reforma's readiness (the `IBSCBS` group of NT 2025.002), the
credit lost on Simples suppliers and the cash split payment holds back. It owns `nfe_imports` and
`nfe_documents`, and the raw files under `nfe/` on the private `local` disk. F6a built the import of
one XML; F6b the ZIP, the folder command (`nfe:import`), the batch list and the seed; the screens'
maths are F7–F9.

## The routes (`routes/api.local.php`)

| Route | Permission | What |
| --- | --- | --- |
| `POST fiscal/imports` | `fiscal.import` | multipart `file` (one `.xml` or `.zip`, ≤ 20 MB) → 201 `{id, source, user_id, by_name, files, stored, refused: [{file, code}], at}`; throttle 10/min, prefix `nfe-import` |
| `GET fiscal/imports` | `fiscal.import` | the firm's batches, newest first (`at`, then id), paginated (`page`, `per_page` ≤ 100, default 20), the same shape; `by_name` is the importer's `users.name`, null for the command and the seed |

And the command `php artisan nfe:import <folder> --firm=<slug>` (`app/Console/Commands/ImportNfeFolder`,
auto-discovered like `appointments:remind`): one batch of source `folder`, no user; prints the counts
and a table of the refusals; exit 1 for an unknown or absent firm, or a folder it refuses.

## Intentional Decisions

- **`POST fiscal/readiness/{id}/notice` and `POST fiscal/supplier-credit/{id}/review` (F7) write under
  `fiscal.read` on purpose (plan AM13).** They only add a line to the client's timeline (`nfe.aviso`,
  `fornecedores.revisao`) — no fiscal data changes — so the team that reads the screens may register
  them. `fiscal.import` is for what writes documents. Reviewers: do not reopen this.
- **One code path (R15)**: `NfeImporter::import(NfeDocumentSource, NfeImportSource, ?userId)` is what
  the upload, the folder command and the seeder call. Sources are chosen by the caller — no container
  binding on `NfeDocumentSource` (D5): `UploadedNfeSource` (the screen), `FolderNfeSource` (the
  command) and the seed's `Database\Seeders\Support\GeneratedNfeSource`. Only local drivers exist: no
  SEFAZ, LLM or bank client, and `tests/Feature/NoOutwardClientsTest` (A24) keeps it so. The importer
  stamps `firm_id` on every row itself — never left to `BelongsToFirm`'s `creating` event — because
  `DatabaseSeeder` runs under `WithoutModelEvents` and the seed imports through this same path.
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
- **Upload ceilings (plan Defaults)**: the request takes one `.xml` or `.zip` of up to 20 MB (422 past
  it, before any read); the source refuses an XML over 1 MB `too_large` from its size alone.
- **ZIP safety (plan AM3, A14)**, in `UploadedNfeSource`: read in memory entry by entry through
  `getStreamIndex`, nothing extracted to disk. The sizes the archive declares (`statIndex`) are never
  read: each entry is read at most 1 MB + 1 byte (more → `too_large`; a bomb or a forged size never
  costs more than that), and the archive at most 20 MB in all (every entry after it is `too_large`,
  unread). Skipped and not counted in `files`: directories, any name not ending `.xml` (case-insensitive
  — so a nested `.zip` too) and macOS's `__MACOSX/` / `._*` shadows. More than 2000 entries → the
  archive is one `too_large` line under its own name; a `.zip` that does not open is one `not_xml`. An
  entry is labelled by its base name only (`../../x.xml` → `x.xml`); its name never becomes a path.
- **Folder safety (plan AM3, A14)**, in `FolderNfeSource::open`: the argument must not itself be a
  symbolic link; it is resolved with `realpath` and must be a directory. Only the `.xml` files directly
  inside it are read, in name order — no subfolder, no symbolic link (to a file or a folder), no other
  name — each through its stream at most 1 MB + 1 byte, whatever the file system says its size is.
- **The seed (`NfeSeeder`, plan AM7)**: 40 Presumido/Real clients, 90 days, through `NfeImporter` (one
  batch per client, `folder`, no user), documents built by `NfeXmlBuilder`; it first deletes
  `nfe/<firm id>/`. It refuses production AND the `testing` environment — the Pest suite builds its own
  documents and never runs it (`tests/Feature/Fiscal/NfeSeederTest`).

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
- **A ZIP's skipped entries are not reported**: a PDF or a nested ZIP inside the archive is silently
  left out (AM3's "skip"), so the batch does not say it was there. `files` counts only `.xml` entries.
- **The folder command does not recurse**: a client's XMLs in subfolders need one run per folder.
