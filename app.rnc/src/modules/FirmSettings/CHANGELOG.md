# Changelog — FirmSettings Module

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.1 — 2026-11-14

### Added

- Chrome QA 3.4: `FirmContactView` spec pins that the first "Salvar" and the next each toast once (the QA saw none on the first; not reproduced, W125).

## 1.0.0 — 2026-11-14

### Added

- The firm's settings for its manager (rnc-4 F5b, R30–R33): `FirmContactView` (`/gestao/escritorio`; address, phone, mailbox, credentials line, business hours with `MBusinessHoursEditor`), `FirmTeamView` (`/gestao/equipe`; list, new login, edit, deactivate/reactivate, password reset shown once — `MAddEditTeamMemberForm`, `MTeamPasswordForm`), `FirmSpecialistsView` (`/gestao/especialistas`; weekly hours with `MSpecialistHoursForm`), `FirmConsentView` (`/gestao/consentimento`; current version, preview with the firm's name, history with version/date/author). Composables `useOneTimePassword` (W81) and `useApiRefusal`. Services for `firm/settings`, `specialists`, `firm/consent-versions` and the team's create/update on `auth/users`. Tests: the four view specs, `MAddEditTeamMemberForm.spec.ts` (A39), `e2e/firm-settings/settings.spec.ts` (A40).
