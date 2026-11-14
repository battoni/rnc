# Changelog — Auth Layouts

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.2.2 — 2026-11-14

### Changed

- `AuthFlowLayout` draws `MFirmLogo` (the host firm's mark) in the aside and above the card (rnc-4 F2c2, R15).

## 1.2.1 — 2026-09-17

### Fixed

- The brand aside painted its pattern, mark and copy with `text-ink-900`/`text-ink-950` on `bg-primary-500`. It now takes `text-primary-contrast` (same opacities), so a theme with a dark primary gets a readable hero (CEL-04, ltv-071). In `battoni-dev` the only visible change is the headline, from ink-950 to ink-900 (the contrast token). Covered by `AuthFlowLayout.spec.ts`.

## 1.2.0 — 2026-09-16

### Changed

- The hero's strings (">\_ app.rnc", "Built to move fast.", the tagline, "yourbrand.dev") are `auth.brand.*` keys; a project overrides them in `src/locales/local/*.json` instead of editing this MERGE file (largatudoevai-139). Covered by `AuthFlowLayout.spec.ts`, including a local override.

## 1.1.2 — 2026-09-15

### Fixed

- Main column `py-6` → `py-4`. Password flow still had a few pixels of scroll at 360×800 because
  the card plus the “need help” bottom action outgrew the remaining height.

## 1.1.1 — 2026-09-15

### Fixed

- Compact vertical chrome (`py-6` / logo `mb-4` / signature `pb-4`) so login phone, password, and
  other AuthFlowLayout flows fit a 360×800 viewport without vertical scroll. Prior `py-10` /
  `mb-8` / `pb-8` overran the card height on those screens.

## 1.1.0 — 2026-06-13

### Changed

- Redesign the auth-flow layout with brand atmosphere and the authorship signature.

## 1.0.0 — 2026-05-30

### Added

- Initial documented baseline.
