# Changelog — MImageUpload

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.2 — 2026-09-17

### Fixed

- A drag marks the zone with `border-primary-500` only; the `bg-primary-50` tint is gone. It does
  not flip with the scheme, and the zone's `text-muted` words on it measured 3.34:1 in dark (from
  chapaja 7445ba9, where the same fix landed on a resolution option).

## 1.0.1 — 2026-09-16

### Fixed

- The file name and the hint: `text-subtle` → `text-muted`. `subtle` measured 1.72:1 on the panel in `battoni-dev` light and never carries a sentence now; readable text is `muted` (theme 2.3.0).

## 1.0.0 — 2026-09-01

### Added

- First release. One image, three ways in: the file picker, a drag onto the drop zone, and a paste
  from the clipboard. The third is the reason the unit exists — the images people attach are
  screenshots, and a screenshot starts in the clipboard. Asking somebody to save it to disk first
  is three steps to undo what the operating system already did.
- Type and size are checked before the parent ever sees the file, and a refusal says which rule it
  broke rather than failing silently.
- `currentUrl` shows an image that is already stored, so editing an entry is not an empty box.
- Emits `onChange(File | null)` — `null` is an explicit clear, which is how a parent tells "remove
  the stored image" from "leave it alone".
