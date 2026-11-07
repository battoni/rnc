# app.rnc — context for AI agents

Vue 3.5 (`<script setup lang="ts">`), TypeScript, PrimeVue (auto-imported), Tailwind, Pinia, Vue
Router, Vue I18n, Yup through PrimeVue Forms.

The conventions are the `../.claude/rules/celer-01` … `celer-09` rules, which load by `paths:` when a
Vue/TS file here is read, and the `RULES.md` chain the pre-edit hook injects. Do not restate them.

## Facts that are easy to get wrong

- **No component imports.** `unplugin-vue-components` registers everything in `src/modules/*`,
  `src/components/*`, `src/layouts/*` and PrimeVue — a component "used nowhere" by grep may be used
  in a template.
- **Modules are imported only through their barrel alias** (`@AuthModule`, `@Helpers`,
  `@Composables` — see `vite.config.ts`). Searching for a symbol's users means searching for the
  name, not for the file path.
- **ESLint fixes on save**: import order, attribute order, `import type`, no-else-return.
- Reviews: `/battoni-review-vue-conventions`. A folder's `RULES.md`: `/battoni-generate-component-rules`.
