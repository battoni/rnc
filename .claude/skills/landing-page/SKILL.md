---
name: landing-page
description: "Build a new high-performance marketing landing page, or review an existing one, for a Battoni client/product. Astro static-first, CodeLumen design tokens, scored against the seven pillars (Performance, UX, UI, SEO, AEO, GEO, Conversion). Load when asked to scaffold/create a landing page, a public.* or marketing site, a product page; when optimizing one for Lighthouse / Core Web Vitals / SEO / AEO / GEO / AI-SEO / structured data / llms.txt; or to review/audit an existing landing page. Canonical template: public.battoni.dev. Method: the landing-page recipe (codelumen/landing-page-recipe.md where codelumen is kept)."
license: MIT
metadata:
  author: rnc
---

# Landing Page — Build & Review (Astro)

> **Scope:** marketing/landing pages for real Battoni clients and products. Static-first
> Astro + CodeLumen design tokens, engineered across the **seven pillars**: Performance,
> UX, UI, SEO, AEO, GEO, Conversion.
>
> - **Method (do not duplicate here):** the landing-page recipe — pillars, targets, templates,
>   do-not list. It lives at `codelumen/landing-page-recipe.md` **in a repo that keeps
>   `codelumen/`** (rnc always does).
> - **Canonical template / reference build:** `public.battoni.dev/`.
> - **Deterministic engine:** `public.battoni.dev/scripts/audit.mjs` — the parts of the
>   recipe verifiable without a live audit tool. Used by both modes.
>
> **When this repo has neither.** A project may delete `codelumen/`, and `public.battoni.dev/`
> is never synced, while this skill is. Check both paths first. If they are missing, read them
> from the rnc checkout (ask the user where it is) — do not guess the recipe's rules from
> memory. Without the recipe, what this file states is the enforceable minimum: the Mode A
> length targets, the gate and the Guardrails below; say in the report that the judgement
> pillars were reviewed without it.

## When to load this skill

Load when the user wants to **create** a landing page (a new client/product page, a
`public.*` or marketing site) **or review** an existing one (Lighthouse, Core Web Vitals,
SEO/AEO/GEO, AI-SEO, structured data, `llms.txt`, conversion). If unsure which mode, ask.

Do **not** load for the app.rnc SPA, the api.rnc API, or the docs portal — those have their
own rules/skills.

---

## Mode A — Create a new landing page

The template is content-centralized and multilingual: **all on-page copy lives in
`src/data/`** — `brand.ts` (name, domain, contact), `locales/{locale}.ts` (the landing page
and the chrome, one dictionary per language) and `pages/*.ts` (About, FAQ, blog chrome).
Components ask `getContent(Astro.currentLocale)`; copy interpolates `SITE_NAME`, so the
brand cascades from one value. A new page is primarily a data edit — not a component rewrite.

Mechanisms the template ships (the recipe has the rules for each): Astro `i18n` from one
table (`src/data/i18n.ts`, pt-BR at the root, `en` under `/en/`), hreflang derived from a
page's locale-relative `path`, a blog content collection, institutional pages as
template + data pairs, a build-time fetcher that fails loudly (example catalogue, **off**
by default) and one declared island (`LiveList`) for live values.

1. **Copy the template.** Duplicate `public.battoni.dev/` to the new project dir
   (e.g. `public.<client>.dev/` or the agreed name). Keep the scaffold: `astro.config.mjs`,
   `vercel.json`, `public/` (favicon, robots.txt, llms.txt), `src/`, `scripts/audit.mjs`.
   Do **not** copy `dist/`, `node_modules/`, `.astro/`.
2. **Confirm real inputs with the user before writing copy.** Brand name, domain, value
   proposition, real features/process, real testimonials & partners (or clearly-labelled
   placeholders), contact email. **Never fabricate** testimonials, statistics, author
   personas, credentials, or hero/product imagery (see recipe "Do-Not-Do").
3. **Decide the languages first** (`src/data/i18n.ts`). Removing one means deleting its
   row, its dictionary and its route folder; adding one is the reverse. Then **edit the data
   files**: `brand.ts`, and in each `locales/{locale}.ts`: `meta` → `chrome` → `nav` →
   `hero` → `features` → `process` → `pillars` → `citations` → `testimonials` →
   `partners` → `faqItems` → `about` → `footer` → `sections` → `liveList`; then
   `pages/about.ts`, `pages/faq.ts`, `pages/blog.ts`, and replace the example posts in
   `src/content/blog/`. Honour the length targets in the recipe (title 50–60, meta
   description 120–155, hero answer 30–50 words, 6+ FAQ items, 800+ words total) **in
   every language** — the audit reads every page.
4. **Update the domain everywhere it is real:** `brand.ts` `SITE_URL`, `astro.config.mjs` `site:`,
   `public/robots.txt` sitemap URL, `public/llms.txt` links. Update schema names if needed.
5. **Add a new section only if the content needs one** — follow Atomic Design
   (atoms → molecules → organisms) and add its copy to the dictionaries' `sections` (and
   `types.ts`), never inline. New sections need an `id` anchor + an entry in the footer nav.
   A new PAGE is a `templates/XPage.astro` + `data/pages/x.ts` pair and a two-line route
   file under every locale prefix.
5b. **Live or API-driven data only if the product has it.** A catalogue read at build time:
   enable `CATALOGUE_ENABLED`, replace the placeholder types, set `PUBLIC_API_URL`. A live
   value: mount `LiveList` and add the API origin to `connect-src` in `vercel.json`.
6. **Only touch legal links (`/privacy`, `/terms`) if the pages exist.** If they don't,
   remove those links from `footer.legal` and `llms.txt` rather than shipping dead links.
7. **Run the gate** (below). Fix every `FAIL`; justify any remaining `WARN`.
8. **Deploy to a Vercel preview**, then run the live external audits from the recipe
   (PageSpeed, GTmetrix, AI-SEO, Rich Results, VWO) — these require a public URL.

## Mode B — Review an existing landing page

1. **Run the deterministic audit first** — it's the objective baseline:
   - In-repo built project: `node public.battoni.dev/scripts/audit.mjs --dir <projectDir>`
     (the project must have a built `dist/`; run `npm run build` there first).
   - Live URL: `node public.battoni.dev/scripts/audit.mjs --url https://the-site.com`.
2. **Report `FAIL`s as must-fix, `WARN`s as recommendations.** The audit covers: single
   H1, heading hierarchy, JSON-LD validity + required/recommended schema types, no Google
   Fonts, internal-anchor integrity, title/meta lengths, canonical, Open Graph, skip link,
   security headers, `llms.txt` spec, robots sitemap.
3. **Then do the judgement-based pillars the script can't check** — read the page against
   the recipe: UX/conversion (hero answer target, CTA clarity, form friction, mobile),
   UI (CodeLumen tokens, contrast, radius scale), content depth & E-E-A-T (real About,
   author bio, citations, cluster links), and the GEO/AEO content quality.
4. **Produce a structured report:** group findings by pillar, mark severity, cite the
   recipe section, and give the concrete fix. Do not edit files unless asked.

---

## The gate (both modes, for an in-repo project)

```bash
cd <project>
npm install
npm test          # astro check + unit tests + astro build + node scripts/audit.mjs
```

`npm test` must be green (0 FAIL) before declaring a build done or a review clean.
`npm run audit` re-runs only the deterministic checks against an existing `dist/`.

## Guardrails (from the recipe — enforce, don't restate in full)

- Zero client JS / hydration, except a declared island for a value that changes faster than a
  deploy (data-* config, loading/empty/error states, business time zone constant).
- A build-time fetch never falls back to empty: unset env, unreachable API or non-2xx fails the build.
- Every page passes its locale-relative `path`; canonical and hreflang are derived, never typed.
- Self-hosted fonts only (Astro Fonts API) — never `fonts.googleapis.com`/`gstatic.com`.
- All copy in `src/data/` (per locale); never reintroduce hardcoded headings/labels into `.astro` markup.
- CodeLumen role tokens, not raw hex / Tailwind palettes.
- Structured data: Organization + WebPage always; Article/FAQPage/HowTo as applicable.
- `llms.txt` per spec; the landing FAQ's answers visible (a FAQ page may use native `<details>`); cite real sources only.
- Never fabricate content, imagery, testimonials, or a Person/author schema.
