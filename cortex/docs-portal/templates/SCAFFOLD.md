# Scaffold contents & setup

Copy everything in this folder into a new `docs.<project>/`, then follow the steps below.
The full method is in `../GUIDE.md`; the rules are in the `docs-portal` skill.

> **Note on `index.md` / `README.md`:** the copies here use `{{PLACEHOLDERS}}`. The
> reference builds (`docs.trainingbeta`, `docs.impressao`) have richer, project-specific
> versions — skim those for the home page, the "★ For you" client section, and page voice.

## What's here

```
.vitepress/
  config.mts                 # site config + ::: containers + anti-FOUC  → set {{PLACEHOLDERS}}, edit nav
  sidebar.ts                 # the ONE sidebar → edit to your real pages (the PDF export reads it too)
  theme/index.ts             # registers components + injects ModeToggle + the PDF link (reusable as-is)
  theme/pdf/*.ts             # PDF export: page list from sidebar.ts, markdown prep, render (reusable as-is)
  theme/pdf/pdf-export.test.ts # node --test for the export + the drift checks (reusable as-is)
  theme/components/PdfExportPage.vue   # /export-pdf: cover, contents, body, print footer (translate COPY)
  theme/components/PdfExportButton.vue # the "PDF" link in the nav (reusable as-is)
  theme/styles/pdf-export.css # export page + print stylesheet (reusable as-is)
  theme/mode.ts              # User/Dev state + localStorage + html class (reusable as-is)
  theme/components/*.vue     # ModeToggle, DevOnly, UserOnly, EndpointTable, SchemaBook, ViewNavMap (reusable)
  theme/styles/brand.css     # set {{BRAND_HEX}} + {{BRAND_FILL_TEXT_HEX}}
  theme/styles/mode.css      # the show/hide rules (reusable as-is)
  theme/styles/components.css # data-table + dev-block styling (reusable as-is)
scripts/
  extract.mjs                # orchestrator → set {{API_DIR}}/{{APP_DIR}} in generatedFrom strings
  extract-endpoints.mjs      # ADAPT: sibling path + your routes-file conventions
  extract-schema.mjs         # ADAPT: sibling path + your migration conventions
  extract-views.mjs          # ADAPT: sibling paths + your SPA route/nav conventions
  lib/modules.mjs            # REPLACE: example map — set your modules + table ownership
middleware.ts                # Vercel Edge gate wiring (reusable; reads env)
middleware/auth-gate.mjs     # pure gate logic (reusable as-is)
middleware/auth-gate.test.mjs # node --test for the gate (reusable as-is)
public/robots.txt          # noindex by default — the safe answer for a portal whose
                           # gate a project may legitimately delete (reusable; set {{TITLE}})
index.md  README.md          # set {{PLACEHOLDERS}}, then write real content
export-pdf.md                # mounts <PdfExportPage /> at /export-pdf (reusable as-is)
package.json  vercel.json  .env.example  .markdownlint-cli2.jsonc  .gitignore
```

Create these by hand (not templated — they're pure content): `guide/`, `concepts/`,
`operations/`, `reference/` pages, and `public/logo.svg`. See the content map in GUIDE.md.

## Placeholders to replace

| Placeholder | Meaning | Example |
| --- | --- | --- |
| `{{SLUG}}` | lowercase slug; localStorage key + session-cookie name | `trainingbeta` |
| `{{TITLE}}` | site / hero title | `TrainingBeta Programs` |
| `{{TAGLINE}}` | hero tagline | `How the new system works — for you and the next dev.` |
| `{{DESCRIPTION}}` | site + package description | `Documentation portal for …` |
| `{{API_HOST}}` | API base URL incl. `/api` | `https://api.trainingbeta.com/api` |
| `{{APP_LOGIN_URL}}` | app login (gate bounce) | `https://app.trainingbeta.com/login` |
| `{{API_DIR}}` | sibling API folder name | `api.trainingbeta` |
| `{{APP_DIR}}` | sibling SPA folder name | `app.trainingbeta` |
| `{{STAFF_PERMISSION}}` | permission marking staff | `builder.access` |
| `{{STAFF_ROLE_SLUG}}` | staff role slug | `staff` |
| `{{BRAND_HEX}}` | primary brand color | `#2f6fb0` |
| `{{BRAND_FILL_TEXT_HEX}}` | label colour on a brand fill (the active User/Developer switch, the PDF button) — must clear 4.5:1 on `{{BRAND_HEX}}`: dark ink for a light brand, `#ffffff` for a dark one | `#ffffff` |

Quick substitution (run inside the new `docs.<project>/`, set the values first):

```bash
slug=trainingbeta; title="TrainingBeta Programs"   # … set the rest
grep -rl '{{SLUG}}' . --include='*' | xargs sed -i '' "s/{{SLUG}}/$slug/g"
# repeat per placeholder, or do it in your editor with find/replace.
```

## Steps

1. Replace all `{{PLACEHOLDERS}}`.
2. `scripts/lib/modules.mjs` — replace the example with your modules + table ownership.
   Every table needs an owner and every PHP/Vue module a docs area in `MODULES`:
   `docs:extract` lists what is missing and refuses to write. Each area with endpoints or
   tables then needs its `<EndpointTable module>` / `<EntityDiagram module>` on the
   reference pages.
3. Each `scripts/extract-*.mjs` — fix the sibling path(s) and adapt the parsing to your
   stack's conventions if they differ from Laravel/app.rnc.
4. `config.mts` — edit `nav`; `sidebar.ts` — edit the sidebar to your real pages (dev group
   `collapsed: true`). The PDF export prints every sidebar page outside `/reference/`, in
   sidebar order, so there is no second list to keep.
5. `npm install && npm run docs:extract` — sanity-check `.vitepress/data/*.json`.
6. Write content (`guide/`, `concepts/`, `reference/`, `operations/`), user-first with
   `::: dev` asides. Add a `public/logo.svg`.
7. `npm run docs:dev` — verify the toggle, no FOUC, search finds dev-only text. Open
   `/export-pdf`: the contents match the sidebar, no dev-only text shows; save a PDF once.
   If the portal is not in English, translate the `COPY` object in `PdfExportPage.vue` (and
   the button's title); rename `export-pdf.md` and `EXPORT_PATH` together if you localise
   paths.
8. `npm test` — gate logic, extractor, and the PDF export. **It fails until every sidebar
   link has its markdown file** (the "every exported page has a markdown file" test); that
   is the point, not a scaffold bug.
9. Gate: set the Vercel env from `.env.example`. Or delete `middleware*` for a public site
   (then drop `middleware/**` from the `test` script).

**PDF export — the two hand-kept lists.** A new data component in `theme/index.ts` must be
added to `PDF_INTERACTIVE_COMPONENTS` (`theme/pdf/prepareMarkdownForPdf.ts`); a new
developer-only folder must be added to `DEVELOPER_ONLY_PREFIXES` and to the
`import.meta.glob` exclusions in `PdfExportPage.vue`. `npm test` fails when either drifts.
The printed version is `package.json`'s `version` — bump it when user pages change materially.
The test script uses `node --experimental-strip-types` (Node 22.6+) to run the `.ts` test.
