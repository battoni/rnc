# docs.{{SLUG}}

The documentation portal for {{TITLE}} — a VitePress site with a **User / Developer**
depth toggle: plain-language guidance for the owner/end user, and schema/endpoint/
architecture depth for the next developer, from one set of pages.

## Develop

```bash
npm install
npm run docs:dev        # extract codebase data + start the dev server
```

## Build & preview

```bash
npm run docs:build      # extract + build static site to .vitepress/dist
npm run docs:preview
```

## Generated reference

The schema / endpoints / views reference is **extracted from the sibling sub-projects**
(read-only) into `.vitepress/data/*.json`:

```bash
npm run docs:extract        # regenerate the data
npm run docs:extract:check  # CI: fail if committed data is stale
```

Module ownership + the codebase paths live in `scripts/lib/modules.mjs` and the top of
each `scripts/extract-*.mjs` — edit those for this project. `docs:extract` refuses to write
when a table has no owner in `TABLE_MODULE`, or an endpoint or view belongs to a module missing
from `MODULES` — each would otherwise vanish from the reference pages or land in the wrong one.

## Tests & lint

```bash
npm test                # node --test — access gate, extractor, PDF export
npm run lint            # markdownlint
```

## Known gaps

- **`npm audit` reports vite 5 (high) and esbuild (moderate) through VitePress.** VitePress 1.6.4
  is the latest stable release and pins `vite ^5`; the fixes live in vite 6.4.3+, which only
  VitePress 2 (still alpha) uses. Every advisory is about the **dev server** (path traversal,
  `fs.deny` on Windows, cross-origin requests to it); the deployed site is static HTML from
  `vitepress build` and runs none of it. Do not expose `npm run docs:dev` beyond localhost, and
  move to VitePress 2 when it leaves alpha. Forcing vite 6 with an override was not done: it
  puts VitePress outside the range it was built against, in a template every portal copies.
- **`smol-toml` is overridden to `^1.8.0`.** `markdownlint-cli2` pins the vulnerable `1.7.0`
  exactly. Drop the override once a release of it stops pinning.

## Export to PDF

`/export-pdf` (the **PDF** link in the top bar) puts the user documentation on one page
(every sidebar page except the developer reference) with a cover, the version from
`package.json`, contents and an out-of-date notice. Its **Save as PDF** button opens the
browser's print dialog. The page list comes from `.vitepress/sidebar.ts`, so a page added to
the sidebar is in the next export. Developer-only content is left out. Bump `version` when user
pages change materially, so a printed copy can be told apart from the live site.

## Access (staff-only)

The portal is gated by a **Vercel Edge middleware** (`middleware.ts`) that forwards the
visitor's `{{SLUG}}-session` cookie to `GET {API_URL}/auth/me` and serves the page only
for a **staff** user. Configure it in Vercel — see `.env.example`. Link here from the
app's staff-only nav. (Delete `middleware.ts` + `middleware/` to make the docs public.)

## Deploy

Vercel project with `buildCommand: npm run docs:build`, output `.vitepress/dist`
(`vercel.json`). Set the env from `.env.example` in the project settings.
