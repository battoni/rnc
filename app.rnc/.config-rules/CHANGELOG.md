# Changelog — app.rnc Build & Config

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.2.0 — 2026-09-12

### Added
- `scripts/check-aliases.mjs` + `npm run check:aliases`, wired into CI: asserts the module
  aliases declared in `vite.config.ts`, `tsconfig.app.json` and `vitest.config.ts` agree, in
  both directions. Proven by mutation in all three files.

### Fixed
- The aliases entry named TWO files that must stay in lockstep. There are three —
  `vitest.config.ts` was missing from the contract, and it is the one that gets forgotten.
- Removed five aliases declared only in `vitest.config.ts` (`@App`, `@Components`, `@Layouts`,
  `@Modules`, `@Router`). Nothing used them, and `@Router` resolved to `src/router`, which does
  not exist. That is the dangerous direction: a source file importing `@Components` would have
  passed the suite and broken the build.
- `vitest.config.ts` gave `VueI18nPlugin` `./src/locales/**` while vite gave `./src/locales/*.json`,
  so the suite precompiled the core/local overrides that must reach the merge as plain strings.
  Reproduced before fixing — a local `Hello {name}` and a local override of `errors.unexpected`
  both failed under `**` and passed under `*.json` — and guarded by
  `src/libraries/i18n/localOverrides.spec.ts`. Found by chapaja.

## 1.1.0 — 2026-07-17

### Added
- `eslint.config.ts`: `vue/no-restricted-syntax` bans `t(...)` inside `<template>` — templates
  must use the globally-injected `$t()`; script-scoped `t()` (yup/toasts) is unaffected. See
  `celer-04-vue-template` (i18n section) and `celer-01` checklist. Existing templates were swept
  `t()` → `$t()` alongside this.

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
