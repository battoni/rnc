# Changelog — Fiscal Module (api)

All notable changes to this unit are documented here. Versioned with SemVer.

## 1.0.0 — 2026-11-07

### Added

- The NF-e import of one XML (rnc-2 F6a, R15–R17, D5–D7, A13, A14's XML half, A15; plan AM3, AM4, AM13): `POST fiscal/imports` (`NfeImportController`, `StoreNfeImportRequest`, `NfeImportResource`) behind `fiscal.import`, throttled `nfe-import` 10/min; `NfeImporter` (the one code path), `NfeXmlParser` (DOCTYPE refused before `loadXML`, `LIBXML_NONET`, tags by local name, item checks), `AccessKey` (mod-11), `NfeDocumentSource` + `UploadedNfeSource`, `NfeFile`/`ParsedNfe`, `NfeRefusal`/`NfeDirection`/`NfeRecipientKind`/`NfeImportSource`, `NfeRefusedException`; `NfeImport`/`NfeDocument` and their repositories; the `nfe_imports` and `nfe_documents` tables. Tested by `tests/Feature/Fiscal/NfeImportTest`, `tests/Unit/Modules/Fiscal/AccessKeyTest` and `NfeXmlParserTest` (fixtures: `tests/Support/NfeFixture`).
