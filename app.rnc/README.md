# app.rnc

A Vue 3 bootstrap application — the starting point for new projects.

## Tech Stack

| Layer | Technology |
| ----- | ---------- |
| Framework | Vue 3.5 + TypeScript (`<script setup>`) |
| UI | PrimeVue 4 + PrimeIcons + Tailwind CSS 4 |
| Icons | Font Awesome 7 |
| State | Pinia |
| Routing | Vue Router |
| i18n | Vue I18n |
| Forms | PrimeVue Forms + Yup |
| HTTP | Axios |
| Error tracking | Sentry |
| Build | Vite 7 |

## Getting Started

```bash
npm install
npm run dev
```

Other scripts:

```bash
npm run build       # type-check + production build
npm run lint        # ESLint --fix (also runs on save in editor)
npm run format      # Prettier
npm run type-check  # vue-tsc
```

## Project Structure

```text
src/
  components/       # Shared components  (A = atom, M = molecule, O = organism)
  composables/      # Shared composables
  helpers/          # Shared helpers
  layouts/          # Layout components
  modules/          # Feature modules
    Auth/
    Home/
    User/
  router/
  stores/
  i18n/
```

Each module follows this internal structure:

```text
ModuleName/
  components/
  services/
  store/
  views/
  enums.ts
  constants.ts
  interfaces.ts
  types.ts
```

## Key Conventions

- Components are **auto-imported** via `unplugin-vue-components` — never import `.vue` files manually
- PrimeVue components are also auto-imported via `PrimeVueResolver`
- Module aliases: `@Helpers`, `@Composables`, `@Interfaces`, `@UserModule`, `@AuthModule`, etc. (see `vite.config.ts`)
- ESLint **auto-fixes on save** — imports, attribute order, `import type`, no-else-return, empty catches

## AI Agents

This project is set up for AI-assisted development.

- **Cursor** auto-loads conventions from `../.cursor/rules/celer-*.mdc`, mirrored from the canonical `../.claude/rules/celer-*.md`
- **Claude Code** reads `CLAUDE.md` at startup and loads `../.claude/rules/celer-*.md` by glob
- Use `/battoni-review-vue-conventions` (`../.claude/commands/`) to review the branch's app.rnc changes against the conventions and fix them
- Use `/battoni-generate-component-rules` to generate a `RULES.md` for any folder
- Component-specific rules live in `RULES.md` files placed inside the relevant folder

## Known Gaps

- **`quill` 2.0.3 carries GHSA-v3m3-f69x-jf25** (XSS via Quill's HTML export, low severity in `npm audit`). It stays because there is no fixed release: 2.0.3 is the latest `quill`, and the only "fix" `npm audit fix --force` offers is a downgrade to 2.0.2, which npm itself flags as breaking. Quill reaches production through PrimeVue's `Editor`, which emits `quill.getSemanticHTML()` — the export path the advisory is about — in `MAddEditTipForm` (Tip) and `MNewsForm` (Idea/News). The mitigation is on the API: `TipContentSanitizer` and `NewsBodySanitizer` clean that HTML against an allow-list on the way in, so what is stored and rendered is the sanitised subset, never the editor's raw output (see `api.rnc/app/Modules/Tip/RULES.md` and `api.rnc/app/Modules/Idea/RULES.md`). Revisit when `quill` publishes a release above 2.0.3: `npm audit` stops listing it, and this entry goes.
