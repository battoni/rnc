# Changelog — Fiscal Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.1.0 — 2026-11-07

### Added
- NF-e ZIP, folder command, batch list and seed (rnc-2 F6b, R15, R17, A14's ZIP and command half, A24; plan AM3, AM7): `UploadedNfeSource` reads a `.zip` entry by entry through its stream (≤ 1 MB + 1 byte each, ≤ 20 MB in all, ≤ 2000 entries; directories, non-`.xml` names, nested ZIPs and macOS shadows skipped; `statIndex` never trusted); `FolderNfeSource` (`realpath`, a directory only, no symbolic link followed) and `php artisan nfe:import <folder> --firm=` (`app/Console/Commands/ImportNfeFolder`); `GET fiscal/imports` (`ListNfeImportsRequest`, `NfeImportService`, `NfeImportRepository::paginateNfeImports` with `by_name` from one `users` join). Tested in `tests/Feature/Fiscal/NfeImportTest`, `NfeImportFolderTest`, `NfeSeederTest` and `tests/Feature/NoOutwardClientsTest`.

### Changed
- `POST fiscal/imports` takes `.xml` or `.zip`, and its answer gains `by_name`.
- `NfeImporter` stamps `firm_id` on the batch and every document itself (`NfeImportRepository::createNfeImport` takes the firm id): under `DatabaseSeeder`'s `WithoutModelEvents` the `BelongsToFirm` event never fires, and the seed imports through the importer.

## 1.0.0 — 2026-11-07

### Added

- The NF-e import of one XML (rnc-2 F6a, R15–R17, D5–D7, A13, A14's XML half, A15; plan AM3, AM4, AM13): `POST fiscal/imports` (`NfeImportController`, `StoreNfeImportRequest`, `NfeImportResource`) behind `fiscal.import`, throttled `nfe-import` 10/min; `NfeImporter` (the one code path), `NfeXmlParser` (DOCTYPE refused before `loadXML`, `LIBXML_NONET`, tags by local name, item checks), `AccessKey` (mod-11), `NfeDocumentSource` + `UploadedNfeSource`, `NfeFile`/`ParsedNfe`, `NfeRefusal`/`NfeDirection`/`NfeRecipientKind`/`NfeImportSource`, `NfeRefusedException`; `NfeImport`/`NfeDocument` and their repositories; the `nfe_imports` and `nfe_documents` tables. Tested by `tests/Feature/Fiscal/NfeImportTest`, `tests/Unit/Modules/Fiscal/AccessKeyTest` and `NfeXmlParserTest` (fixtures: `tests/Support/NfeFixture`).
