# Changelog — MPersonalizationVisualCard

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.2 — 2026-09-17

### Fixed

- The card was the retired flat card (`border border-line`, no relief); it is `bg-panel shadow-lift` with no outline (celer-06, card chrome).
- The icon tile wrote `primary-700` on `primary-100`, 1.83:1 in `battoni-dev` — under the 3:1 an icon needs. A pale tint takes the ramp's `950` (theme RULES), 4.54:1. The tile carries `data-testid="personalization-visual-card-icon"`; a new spec covers both.

## 1.1.1 — 2026-09-17

### Fixed

- The card was `rounded-xl`; a card is `rounded-lg` (lifeshifter-482). Covered by a new spec.

### Changed

- `RULES.md`: Purpose lists language and the pinned menu button, which the card already had; a Do Not says a server-backed preference belongs to the User module (lifeshifter-340).

## 1.1.0 — 2026-09-16

### Added

- Language selector, above the display toggles. The card is the one surface for preferences
  (`PersonalizationView/RULES.md`), so the switch belongs here rather than in the navigation.

## 1.0.2 — 2026-07-15

### Changed

- Relocate to `modules/Personalization/components/molecules` (single consumer; domain-specific per `components/RULES.md`).

## 1.0.1 — 2026-07-13

### Changed

- Align markup with approved reference: native section, palette header, `storeToRefs`, icons inside labels.

## 1.0.0 — 2026-07-13

### Added

- Visual preferences card with dark mode, full-width, and tutor toggles.
- Unit tests (`MPersonalizationVisualCard.spec.ts`).
