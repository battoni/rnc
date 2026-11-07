# Changelog — mail views

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.1.0 — 2026-11-07

### Added

- `daily-briefing.blade.php` and its `-text` twin (rnc-3 F8, R27): Reinaldo's 07:00 briefing — the four figures two by two, the day's actions with their links, the next meetings, the referrals by SLA and the button to `/reinaldo`; `lang/{pt_BR,en}/mail.php` → `briefing.*`; preview `/mail-preview/daily-briefing`.

## 1.0.0 — 2026-09-05

### Added

- `layout.blade.php` — table-based chrome with a brand band, hidden preheader and a footer
  that says why the message arrived.
- `partials/_code.blade.php` — a one-time code, monospace and letter-spaced.
- `verification-code` and its text twin, the first message to use the layout.
- `/mail-preview`, local only, so an e-mail can be looked at without being sent.
- The brand mark in the header, embedded as a `cid:` attachment, cropped to the glyph, with
  the name kept as text beside it for the readers whose client blocks images.
