# CodeLumen

Code conventions and best practices documentation for battoni.dev projects — published as a VitePress site.

## Getting Started

```bash
npm install
npm run docs:dev
```

Other scripts:

```bash
npm run docs:gen      # regenerate the rule and command pages from ../.claude (dev and build run it first)
npm run docs:build    # production build
npm run docs:preview  # preview production build locally
node scripts/generate-rules.mjs --check   # exit 1 when a generated page is stale, missing or orphaned
```

## Contents

| Path | Topic |
| ---- | ----- |
| `index.md`, `about.md` | Home and about |
| `monorepo.md`, `repositories.md` | The rnc monorepo and its repositories |
| `technologies.md` | Tech stack decisions |
| `day-to-day.md` | Day-to-day development workflow |
| `git-flow.md` | Git branching and the commit/PR cycle |
| `design-system.md` | The long form of the design system (`celer-06` is self-contained) |
| `icons.md`, `tooltips.md` | Icon and tooltip usage |
| `landing-page-recipe.md` | Method for building a landing page |
| `ai-tooling.md` | The AI layer: rules, commands, skills, hooks |
| `access-control.md`, `middleware.ts` | Who can read the published handbook |
| `rules/`, `commands/` | **Generated** from `.claude/rules` and `.claude/commands` — edit the source, not the page |

**Retired:** the old list (`conventions.md`, `formatting-reference.md`, `architecture.md`,
`concepts.md`, `implementation.md`, `forms.md`, `.cursorrules.template`, and a fallback to
`ai/vue-rules/`). None of those files exist any more; the rules they carried live in
`.claude/rules/` and reach this site through `docs:gen`.
