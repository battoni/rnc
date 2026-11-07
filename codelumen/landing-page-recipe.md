# Battoni Landing Page Recipe

**Version:** 1.1 (September 2026) — languages, build-time data, declared islands, blog and institutional pages  
**Canonical reference implementation:** `rnc/public.battoni.dev/`  
**Live preview:** https://public-battoni-dev.vercel.app/

---

## Purpose

This document is the **authoritative recipe** for building Battoni marketing landing pages. It is written for AI assistants (e.g. Claude) and human developers. Follow it step by step when scaffolding or optimizing any new landing page in the Vitrum (Astro) bootstrap.

**Goal:** Every landing page must score **100/100** on Lighthouse (all categories), maintain **100% GTmetrix** Performance and Structure, pass **Google Rich Results** validation, and achieve a high **AI SEO Audit** score (target ≥ 85/100) without fabricating content, credentials, or assets.

---

## The Seven Pillars

Every landing page is engineered across these seven pillars. Each pillar has concrete, testable requirements.

| Pillar | What it means | Primary validation |
|--------|---------------|-------------------|
| **Performance** | Static-first, minimal JS, fast paint | PageSpeed Insights, GTmetrix |
| **UX** | Clear hierarchy, frictionless navigation, mobile-first | VWO Deep UX, manual QA |
| **UI** | CodeLumen design tokens, consistent components | Design review |
| **SEO** | Crawlable, indexable, semantic HTML | Lighthouse SEO, Rich Results |
| **AEO** | Answer Engine Optimization for snippets & PAA | AI SEO Audit (AEO pillar) |
| **GEO** | Generative Engine Optimization for LLM citation | AI SEO Audit (GEO pillar), `llms.txt` |
| **Conversion** | CTAs, social proof, lead capture | VWO UX, manual QA |

---

## Tech Stack (non-negotiable)

| Layer | Choice | Why |
|-------|--------|-----|
| Framework | **Astro 7** (static SSG) | Zero client JS by default; fast builds |
| Styling | **CSS + CodeLumen design tokens** | Brand consistency; no runtime CSS-in-JS |
| Fonts | **Astro 7 Fonts API + Fontsource** | Self-hosted; no Google Fonts CDN |
| Deployment | **Vercel** | CDN, security headers, preview deploys |
| Content | **Locale dictionaries behind `site.ts`** (`src/data/locales/*.ts`, `src/data/pages/*.ts`) | One source of truth for all copy, per language |
| Languages | **Astro `i18n`** — default locale at the root, the others prefixed | One page tree, hreflang derived from the path |
| Client JS | **None, except declared islands** (`src/scripts/`) | Live values only; everything else static |
| Architecture | **Atomic Design** (atoms → molecules → organisms) | Reusable, predictable structure |

**References:**
- [Astro documentation](https://docs.astro.build/)
- [Astro Fonts API](https://docs.astro.build/en/guides/fonts/)
- [CodeLumen design system](./design-system.md) (`rnc/codelumen/design-system.md`)
- **Canonical template / reference build:** `rnc/public.battoni.dev/` — copy this to
  start a new page. (`rnc/vitrum/` is only the bare Astro bootstrap, not the optimized
  template.)

---

## Project Scaffold

When creating a new landing page, replicate this structure:

```
<project-name>/
├── astro.config.mjs          # site URL, sitemap, fonts, inlineStylesheets
├── vercel.json               # security headers + llms.txt Content-Type
├── package.json              # Astro 7.3+, @astrojs/sitemap
├── public/
│   ├── favicon.svg
│   ├── robots.txt            # allow crawling, reference sitemap
│   └── llms.txt              # LLM crawler instructions (llms.txt spec)
├── .env.example              # PUBLIC_API_URL — only needed once a data source is enabled
├── tests/                    # node --test: build-time fetcher, island helpers
├── src/
│   ├── content.config.ts     # blog collection schema (Zod)
│   ├── content/blog/{locale}/{slug}.md
│   ├── data/
│   │   ├── i18n.ts           # THE locale table — router, sitemap and hreflang all read it
│   │   ├── brand.ts          # name, domain, contact — language-independent
│   │   ├── site.ts           # getContent(locale), pageAlternates(path)
│   │   ├── types.ts          # SiteContent — the shape every dictionary fills
│   │   ├── locales/          # pt-br.ts, en.ts — the landing page's words
│   │   ├── pages/            # about.ts, faq.ts, blog.ts — other pages' words
│   │   └── catalogue.ts      # EXAMPLE build-time data source (off by default)
│   ├── lib/
│   │   ├── structured-data.ts  # JSON-LD builders
│   │   └── fetch-at-build.ts   # build-time fetch that fails loudly
│   ├── scripts/live-list.ts  # the declared island
│   ├── layouts/
│   │   ├── BaseLayout.astro  # meta, canonical + hreflang from `path`, fonts, JSON-LD
│   │   └── LandingLayout.astro  # Organization + WebSite + WebPage schema, skip link
│   ├── pages/                # two-line route files; en/ mirrors the root tree
│   ├── components/
│   │   ├── atoms/            # Button, Icon, Badge, ThemeToggle
│   │   ├── molecules/        # FeatureCard, TestimonialCard, CTABlock, LanguageSwitcher
│   │   ├── organisms/        # SiteHeader, Hero, Features, …, Footer, LiveList
│   │   └── templates/        # LandingPage, AboutPage, FaqPage, Blog*, CataloguePage
│   └── styles/
│       ├── design-tokens.css # CodeLumen tokens
│       ├── live-list.css     # global on purpose: styles nodes a script creates
│       └── global.css
└── dist/                     # build output (gitignored)
```

---

## Page Flow (section order)

Assemble sections in this exact order in `index.astro`:

```
Hero → Features → HowItWorks → CTA → SocialProof → FAQ → About → Contact → Footer
```

Each section must have a unique `id` anchor for internal navigation:

| Section | Anchor ID |
|---------|-----------|
| Hero | (no id — contains H1) |
| Features | `#features` |
| How it works | `#how-it-works` |
| Social proof / Testimonials | `#testimonials` |
| FAQ | `#faq` |
| About | `#about` |
| Contact | `#contact` |

Footer navigation must link to all anchors above plus legal pages when available. Off the home, the
footer prefixes each anchor with the home of the current language (`/en/#faq`) — a bare `#faq` on
the About page points at nothing.

---

## Pillar 1: Performance

### Requirements

1. **Zero client JavaScript except declared islands** — no hydration, no frameworks on the client. The one
   exception is a value that changes faster than a deploy; see [Islands](#islands-the-only-client-javascript).
   *(Was "zero client JS unless strictly required" — retired because "strictly required" was never
   defined; the island rule says what qualifies and how it must behave.)*
2. **Self-hosted fonts** via Astro 7 Fonts API (`fontProviders.fontsource()`). Never use `fonts.googleapis.com` or `fonts.gstatic.com`.
3. **Inlined CSS** — set `build.inlineStylesheets: 'always'` in `astro.config.mjs` to eliminate render-blocking stylesheet requests.
4. **Preload LCP-critical assets** — preload the heading font and hero logo with `fetchpriority="high"`.
5. **Optimized images** — use Astro image pipeline or SVG for logos; specify width/height to prevent CLS.
6. **Trim font weights** — only ship weights actually used in CSS (optional optimization if payload > 70KB fonts).
7. **The page declares its own LCP image.** The layout takes a preload prop and emits `<link rel="preload" as="image" fetchpriority="high">` with `imagesrcset`, `imagesizes` and `media`, so a page whose largest paint differs between phone and desktop preloads the right one.
8. **Preload fonts only where the first fold uses them** — `media="(min-width: 769px)"` on a display font the phone's first screen never shows, so its first connections go to what it paints.
9. **Below the fold, `content-visibility: auto` with a reserved size** (`contain-intrinsic-size: auto 720px`) on each section; a section that measures itself (a carousel) opts out with `content-visibility: visible`.

### `astro.config.mjs` template

```js
import { defineConfig, fontProviders } from 'astro/config';
import sitemap from '@astrojs/sitemap';
import { astroI18nConfig, sitemapI18nConfig } from './src/data/i18n.ts';

export default defineConfig({
  site: 'https://your-domain.com',
  i18n: astroI18nConfig(),
  integrations: [sitemap({ i18n: sitemapI18nConfig() })],
  build: { inlineStylesheets: 'always' },
  vite: { build: { assetsInlineLimit: 0 } }, // scripts as files — see Islands
  fonts: [
    {
      provider: fontProviders.fontsource(),
      name: 'Space Grotesk',
      cssVariable: '--font-space-grotesk',
      weights: [400, 500, 600, 700],
      styles: ['normal'],
    },
    // Add brand body font as needed
  ],
});
```

### Performance targets (validated on reference implementation)

| Metric | Target | Reference result |
|--------|--------|------------------|
| Lighthouse Performance | 100 | 100 (desktop & mobile) |
| GTmetrix Performance | 100% | 100% |
| GTmetrix Structure | 100% | 100% |
| LCP | < 1.2s (good: < 2.5s) | 281ms |
| TBT | < 150ms | 0ms |
| CLS | < 0.1 | 0 |
| Total page size | < 150KB | ~85KB |
| Total requests | < 15 | 9 |

**References:**
- [Core Web Vitals](https://web.dev/vitals/)
- [Largest Contentful Paint (LCP)](https://web.dev/articles/lcp)
- [PageSpeed Insights](https://pagespeed.web.dev/)
- [GTmetrix](https://gtmetrix.com/)

---

## Pillar 2: UX

### Requirements

1. **Skip link** — "Skip to main content" visible on keyboard focus, targets `#main-content`.
2. **Single H1** — exactly one `<h1>` on the page (in Hero).
3. **Logical heading hierarchy** — H2 for sections, H3 for subsections/questions; no skipped levels.
4. **Anchor navigation** — all footer and in-page CTAs scroll to correct sections.
5. **Mobile-first layout** — readable at 375px; touch-friendly CTAs (min 44px tap target).
6. **Contact form UX:**
   - Required fields marked with `*` and a note: "Fields marked * are required"
   - Placeholder text with sufficient contrast (not washed out)
   - Submit button visible immediately after the last field (no excessive vertical gap)
   - Form action: `mailto:` until a backend exists
7. **The landing page's FAQ stays open** — it is the page's answer target; every answer visible.
   A dedicated FAQ page may use native `<details name="faq">` (no JavaScript, answers still in the
   HTML, FAQPage JSON-LD built from the same list). *(Was "No FAQ accordion" — retired because the
   objection was to JS accordions that hide answers from crawlers; `<details>` ships the answer in
   the markup and runs no script.)*
8. **Mid-page CTA** — present but must not overpower surrounding sections.

### VWO Deep UX checks (manual)

- Hero metric labels readable at small sizes
- Section dividers visible between hero metrics and content
- Primary button has clear boundary/edge (box-shadow on lime CTA)
- Footer links and tagline have sufficient contrast
- Testimonial stars visible against white card background
- Testimonial author name prominent; role visible

**Reference:** [VWO Deep UX Analysis](https://vwo.com/deep-ux-analysis-report/)

---

## Pillar 3: UI

### Requirements

Follow the CodeLumen design system (`codelumen/design-system.md`):

1. **Typography roles:**
   - Headings & buttons: Space Grotesk (`--font-sans`)
   - Body: brand font (Sometype Mono in reference) via `--font-mono`
2. **Color tokens** — use role tokens, not raw hex in components:
   - `--color-canvas`, `--color-panel`, `--color-heading`, `--color-body`, `--color-muted`, `--color-line`
3. **Contrast** — `--color-subtle` must meet WCAG contrast on white (use `surface-600` or darker).
4. **Radius scale** — `xs` (2px) → `xl` (10px); cards use `radius-lg`.
5. **Atomic components** — reuse atoms (Button, Badge, Icon) inside molecules and organisms.
6. **No fabricated imagery** — do not add hero screenshots or product mockups unless real assets are provided.

---

## Pillar 4: SEO

### Meta tags (in `BaseLayout.astro`)

| Tag | Requirement |
|-----|-------------|
| `<title>` | 50–60 characters; includes primary keyword |
| `<meta name="description">` | 120–155 characters; summarizes page accurately |
| `<link rel="canonical">` | Absolute URL of the page |
| Open Graph | `og:type`, `og:url`, `og:title`, `og:description`, `og:site_name`, `og:locale` |
| Twitter Cards | `summary_large_image`, title, description |
| `og:image` | An **absolute** URL (a relative one resolves against the crawler), 1200×630 — the 1.91:1 WhatsApp renders as a large card — with `og:image:width`/`height` declared. Versioned (`?v=2`) whenever the image or the text around it changes: crawlers and CDNs cache the card by URL, and replacing the file under the same name keeps the old card with nothing in any log. |

The share tags are written into the HTML, never by a client component: the crawlers that build a link
preview (WhatsApp, Slack, Telegram, iMessage) do not run JavaScript. For a SPA that means `index.html`.

### Crawlability

- `robots.txt` — allow crawling; reference sitemap URL
- `@astrojs/sitemap` — auto-generate `sitemap-index.xml`
- No `noindex` / `nofollow` on the landing page
- Single H1, logical H2/H3 hierarchy

### Languages (hreflang)

Declared once in `src/data/i18n.ts`. The default locale (pt-BR) lives at the root; every other
locale mirrors the **same page tree** under its prefix (`/sobre/` ↔ `/en/sobre/`).

- A page passes its **locale-relative `path`** to the layout (`''`, `'sobre/'`, `'blog/x/'`); the
  layout derives the canonical, one `<link rel="alternate" hreflang>` per locale, and `x-default`
  (the default locale's version). Never a hand-written URL.
- A page that does not exist in every language passes `locales` — a post in one language
  advertises no translation. An hreflang that points at a 404 poisons the whole cluster.
- `@astrojs/sitemap` gets the same table (`sitemapI18nConfig()`), so the sitemap carries the
  `xhtml:link` alternates too.
- `<html lang>`, `og:locale` and `og:locale:alternate` come from the same table.
- The language switcher links to **this page** in the other language, not to its home.
- Slugs are identical across languages. Translated slugs need an explicit map in `i18n.ts`.
- Adding a language is a row in `i18n.ts`, a dictionary in `locales/`, an entry per
  `pages/*.ts`, and the route files under the new prefix. `astro check` fails on a dictionary that
  misses a key; the audit fails on an alternate with no page behind it.

### Semantic HTML

- `<main id="main-content">` wrapping page content
- `<section>` with `aria-labelledby` pointing to section headings
- `<nav>` for footer links with `aria-label`
- `<form>` with proper `<label>` associations

**References:**
- [Google Search Central — SEO starter guide](https://developers.google.com/search/docs/fundamentals/seo-starter-guide)
- [Structured data intro](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data)

---

## Pillar 5: AEO (Answer Engine Optimization)

AEO structures content so search engines, voice assistants, and "People Also Ask" boxes can extract direct answers.

### Requirements

1. **Answer target** — a concise paragraph of **30–50 words** directly below the H1 in the Hero, answering the page's primary topic.
2. **Q&A format** — FAQ questions rendered as `<h3>` headings (not `<dt>`), with answers as `<p>` below — visible on the landing page; on a dedicated FAQ page the `<h3>` sits in a native `<summary>` and the answer is still in the HTML (see Pillar 2, item 7).
3. **FAQPage JSON-LD** — one `Question` / `Answer` pair per FAQ item.
4. **HowTo JSON-LD** — if the page has a process/steps section, add `HowTo` schema with `HowToStep` entries.
5. **Lists** — use `<ol>` or `<ul>` for step-by-step or feature lists (snippet-friendly).
6. **Content depth** — target **800–1500 words** total on the page; address who, what, why, how.

### FAQ schema template

```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Your question here?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Direct answer here."
      }
    }
  ]
}
```

**Reference:** AI SEO Audit AEO checks — https://ai-audit.seogrow.co/

---

## Pillar 6: GEO (Generative Engine Optimization)

GEO optimizes content for AI-powered search engines (ChatGPT, Gemini, Perplexity) and LLM crawlers.

### Requirements

1. **`llms.txt`** at domain root — follow the [llms.txt specification](https://llmstxt.org/):
   - `# Site Name` as H1
   - `>` blockquote summary
   - Free-form paragraph (no extra headings before H2)
   - `## Pages` — links as `- [Title](url): description`
   - `## Optional` — secondary links
   - `## Contact` — mailto link
2. **Serve `llms.txt` as `text/markdown`** — configure in `vercel.json`.
3. **HTML `<table>`** — include at least one structured data table (e.g. feature/pillar comparison).
4. **Verifiable citations** — support data claims with `(Source: source-name)` linked to credible external URLs:
   - Performance claims → [web.dev/vitals](https://web.dev/vitals)
   - LCP thresholds → [web.dev/articles/lcp](https://web.dev/articles/lcp)
   - Structured data → [Google Search Central](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data)
5. **Expert quotes** — testimonials use semantic attribution:
   ```html
   <figure>
     <blockquote><p>"Quote text"</p></blockquote>
     <figcaption>
       <cite>Author Name</cite>
       <span>Role, Company</span>
     </figcaption>
   </figure>
   ```
6. **Article JSON-LD** — add `Article` schema with `author` and `publisher` as `Organization` (use real org; never fabricate a Person).
7. **Modular sections** — each section is self-contained with a clear heading and purpose.

### `llms.txt` template

```markdown
# Your Brand

> One-line summary of what the site offers.

Paragraph describing the company, stack, and crawler policy.

## Pages

- [Home](https://your-domain.com/): Main landing page
- [Features](https://your-domain.com/#features): Key capabilities
- [FAQ](https://your-domain.com/#faq): Common questions

## Optional

- [Sitemap](https://your-domain.com/sitemap-index.xml): XML sitemap
- [Robots](https://your-domain.com/robots.txt): Crawler rules

## Contact

- [Email](mailto:hello@your-domain.com): Project inquiries
```

**References:**
- [llms.txt specification](https://llmstxt.org/)
- [AI SEO Audit](https://ai-audit.seogrow.co/)

---

## Pillar 7: Conversion

### Requirements

1. **Hero CTAs** — primary CTA above the fold (e.g. "Start your project" → `#contact`); secondary CTA to supporting section (e.g. "See how it works" → `#how-it-works`).
2. **Mid-page CTA block** — headline + description + dual buttons between Social Proof and FAQ (or after How It Works).
3. **Social proof** — partner names and/or 3 testimonial cards with attributed quotes.
4. **Contact form** — name, email, company, message; required markers; `mailto:` submit.
5. **Metrics bar in Hero** (optional) — 2–3 quantified outcomes (e.g. "LCP target < 2.5s") with readable labels.
6. **Footer** — brand, navigation anchors, legal links, contact email.
   - **Only link `/privacy` and `/terms` (in the footer and `llms.txt`) when those pages actually exist.** Linking crawlers and visitors to 404s is a quality/credibility signal against the page — omit the links until the pages ship rather than pointing at dead URLs.

---

## E-E-A-T (Experience, Expertise, Authoritativeness, Trust)

Required for high AI SEO Audit scores:

| Check | Requirement |
|-------|-------------|
| About section | `#about` with genuine company description |
| About link in footer | Must link to `#about` |
| Author bio | Visible on-page bio (team or organization — never a fabricated individual) |
| Article schema | `author` + `publisher` = Organization |
| Content depth | 800+ words addressing who/what/why/how |
| Cluster links | Contextual internal links between sections (e.g. Features → `#faq`, About → `#how-it-works`) |
| External authority links | Link to reputable sources (web.dev, Google Search Central) |

**Do not:**
- Invent author personas or credentials
- Create fake cluster/blog pages just to satisfy audit tools
- Fabricate testimonials or statistics

---

## Build-time data: fail loudly, never publish empty

A page generated from an API's data is static — which means that the day the API is down, a naive
build publishes an empty page and every check stays green. `src/lib/fetch-at-build.ts` refuses:

| Situation | Result |
|-----------|--------|
| Base URL env var unset or empty | Build fails, naming the variable |
| Request cannot be made (DNS, refused, timeout) | Build fails, naming the URL |
| Non-2xx answer | Build fails with the status |
| Body is not the `{ data }` envelope | Build fails |

A failed build keeps the last good deploy online. Responses are cached per process (one request per
path, concurrent pages share it; a failure is not cached).

**Opt-in is a constant, not an env var.** The example (`src/data/catalogue.ts`) is off with
`CATALOGUE_ENABLED = false`, so the template builds with no API. Turning it on is a reviewed commit;
from then on a missing `PUBLIC_API_URL` fails the build instead of quietly dropping the pages —
"no env var, no catalogue" is the silent empty page by another name.

The catalogue's types are **placeholders** (`catalogue-types.ts`); a project replaces them with its
API's resource, as it arrives.

## Islands: the only client JavaScript

An island exists only for a value that changes faster than a deploy (availability, a queue,
opening hours). Reference: `organisms/LiveList.astro` + `scripts/live-list.ts`.

1. **The server renders the shell** — section, heading, the loading sentence. The script fills in.
2. **Configuration comes from `data-*` on the root element** — API URL, endpoint, locale, contact
   fallback, every string (JSON). No globals, nothing the page did not hand it.
3. **Honest states.** Loading, **empty** ("nothing scheduled") and **error** ("could not load")
   are different sentences, and both offer the contact fallback. An error never renders as empty.
4. **Times in the business zone.** `BUSINESS_TIME_ZONE` is a named constant at the top of the
   script (default `America/Sao_Paulo`); every date goes through `Intl.DateTimeFormat` with it.
   "Today" is the business calendar's today. The visitor may be anywhere; the thing happens where
   the business is.
5. **API text through `textContent`**, never `innerHTML`; a link is followed only if it is
   absolute http(s).
6. **Business rules stay on the server.** The API sends a status (`open`/`limited`/`full`/
   `closed`); the island labels it and does not compute thresholds.
7. **Scripts ship as files.** `vite.build.assetsInlineLimit: 0` — otherwise Vite inlines a small
   island and `script-src 'self'` blocks it without a word.
8. **The CSP names the API.** Add the API origin to `connect-src` in `vercel.json`. `astro dev`
   sends no CSP, so this only breaks in production — the audit checks it.
9. **Mounted only where the live value is the point**, and only when the API is configured.

## Blog (content collection)

- Posts: `src/content/blog/{locale}/{slug}.md`; schema in `src/content.config.ts` (title,
  description, category, pubDate, locale, optional cover `{ src, alt }`, readingMinutes). A post
  with a bad field fails the build.
- Pages filter on the frontmatter `locale`, which the schema restricts to `i18n.ts`'s locales.
- A translation keeps the **same file name**; that is what links the versions (hreflang, switcher).
- A cover is optional on purpose: no real image, no image — never a stand-in.
- Structured data: `Blog` on the listing (`CollectionPage`), `BlogPosting` + `BreadcrumbList` on a
  post, author and publisher the Organization.

## Institutional pages: template + data

Each page beyond the landing is a pair: `components/templates/XPage.astro` (structure only) and
`src/data/pages/x.ts` (one typed object per locale). The route files are two lines under each
prefix. Reference: About (`AboutPage`, `AboutPage` schema) and FAQ (categories as anchors, native
`<details name>`, `FAQPage` JSON-LD from the same list).

---

## Structured Data (JSON-LD)

Inject via `<script type="application/ld+json">` in `BaseLayout.astro`. Required schemas:

| Schema | Where defined | Purpose |
|--------|---------------|---------|
| `Organization` | `LandingLayout.astro` | Brand identity |
| `WebSite` | `LandingLayout.astro` | Site identity, `inLanguage` |
| `WebPage` (or `AboutPage`, `CollectionPage`) | `LandingLayout.astro` (`pageType`) | Page metadata, `inLanguage` |
| `Article` | `templates/LandingPage.astro` | E-E-A-T; author = Organization |
| `FAQPage` | `LandingPage.astro`, `FaqPage.astro` | FAQ rich results |
| `HowTo` | `LandingPage.astro` | Process/steps rich results |
| `Blog` / `BlogPosting` + `BreadcrumbList` | `BlogListPage.astro` / `BlogPostPage.astro` | Blog |

All of them come from the builders in `src/lib/structured-data.ts`; none is typed out in a page.

Validate after deploy: [Google Rich Results Test](https://search.google.com/test/rich-results)

---

## Security Headers (`vercel.json`)

Ship these headers on all routes:

| Header | Value |
|--------|-------|
| `Strict-Transport-Security` | `max-age=63072000; includeSubDomains; preload` |
| `X-Frame-Options` | `DENY` |
| `X-Content-Type-Options` | `nosniff` |
| `Cross-Origin-Opener-Policy` | `same-origin` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | Restrict sensors, camera, mic, etc. |
| `Content-Security-Policy` | `default-src 'self'; script-src 'self' 'sha256-…'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; form-action 'self' mailto:; frame-ancestors 'none'; upgrade-insecure-requests` — plus `connect-src 'self' <api-origin>` once an island is mounted |

Versioned assets are immutable: `Cache-Control: public, max-age=31536000, immutable` on `/_astro/*`, `/assets/*` and `/fonts/*` — their file names change when their content does.

**Notes:**
- **Inline scripts need their hash in `script-src`.** The template has two, both `is:inline` and both about the theme (the pre-paint read in `BaseLayout`, the toggle). Without the hashes the browser refuses them silently — the reference build shipped that way until the audit learned to check it. Edit either script and `npm run audit` prints the new `sha256` to paste. Processed scripts (islands) ship as files and need no hash.
- If Vercel Web Analytics is enabled, add `/_vercel/insights/script.js` to CSP `script-src`.
- `style-src` includes `'unsafe-inline'` because CSS is inlined for performance (`inlineStylesheets: 'always'`). This is a deliberate, accepted trade-off — Lighthouse Best Practices still scores 100. Do **not** remove inline styling to tighten CSP unless you also re-introduce external stylesheets (which regresses Performance).

---

## Content Checklist (`src/data/`)

**All on-page copy lives in data files, one per language** — section headings, intros, badges,
contact form fields, hero metrics, CTA copy, and the chrome's accessible names included.
Components ask `getContent(Astro.currentLocale)` (from `site.ts`) and never hardcode a
user-facing string. *(Was "a single `site.ts`" — retired when the template became multilingual;
`site.ts` is now the one door to the dictionaries, not the dictionary.)* The brand (name, domain,
e-mail) is `brand.ts`. For each locale in `locales/`, populate:

| Key | Content rules |
|-----|---------------|
| `meta` | title (50–60 chars), description (120–155 chars), Article dates, HowTo name |
| `chrome` | skip link, landmark labels, toggle label, copyright line |
| `nav` | header links to the site's pages (locale-relative paths) |
| `hero` | badge, headline (H1), subheadline (30–50 words), primary + secondary CTA |
| `features` | 4 cards with icon, title, description (include `(Source: …)` where citing benchmarks) |
| `process` | 4 steps for HowTo schema |
| `pillars` | 7 rows for comparison table |
| `citations` | 2–3 verifiable external sources with label, href, source name |
| `testimonials` | 3 quotes with author name + role (real or clearly labeled placeholders) |
| `partners` | partner/company names |
| `faqItems` | 6+ Q&A pairs; questions phrased as real questions |
| `about` | headline, 2 paragraphs, author block (team bio) |
| `footer` | tagline, anchor links, page links, legal links, CTA label |
| `sections` | section chrome: each section's heading, intro, badge, table labels, contact form fields, and the mid-page CTA copy. `intro` fields may contain inline anchor HTML (rendered with `set:html`) for cluster links |
| `liveList` | the island's heading, intro and every runtime string (loading, empty, error, statuses) |

Other pages: `pages/about.ts`, `pages/faq.ts`, `pages/blog.ts` — same rule, one object per locale.

---

## Audit Validation Checklist

### 0. Deterministic audit (run first, no live URL needed)

The template ships `scripts/audit.mjs` — it checks the parts of this recipe that can be
verified without an external tool, and is the gate for both building and reviewing.

```bash
cd <project>
npm test                                   # astro check + astro build + audit (must be 0 FAIL)
npm run audit                              # re-run audit only, against an existing dist/
node scripts/audit.mjs --dir <otherDir>    # review another built project
node scripts/audit.mjs --url https://live  # review a live URL (HTML + headers over HTTP)
```

It reads **every generated page** and verifies: single H1, heading hierarchy, JSON-LD validity +
required (Organization, WebPage or a subtype) schema types, recommended (Article, FAQPage, HowTo)
types **on each locale's landing page**, no Google Fonts, internal-anchor integrity, title (50–60)
and meta-description (120–155) lengths, canonical, Open Graph, skip link; **hreflang** (includes
itself, has `x-default`, every alternate on this site has a built page); **inline scripts allowed
by the CSP** (it prints the `sha256` to add); **each island's API origin allowed by `connect-src`**;
and, site-wide, security headers (`vercel.json` or live), `llms.txt` spec, the robots sitemap and
**sitemap hreflang**. `FAIL` blocks; `WARN` is a recommendation to justify.

`npm test` also runs `npm run test:unit` (`node --test tests/`): the build-time fetcher's refusals
and the island's time-zone and link rules.

The external tools below still require a **live public URL** and cover what the script
cannot (real Lighthouse scores, layout/UX judgement, AI-SEO scoring).

### 1. PageSpeed Insights
- **Tool:** https://pagespeed.web.dev/
- **Target:** 100 Performance, 100 Accessibility, 100 Best Practices, 100 SEO (desktop + mobile)
- **Check:** No Google Fonts requests; no render-blocking CSS

### 2. GTmetrix
- **Tool:** https://gtmetrix.com/
- **Target:** 100% Performance, 100% Structure; LCP < 1.2s, TBT < 150ms, CLS < 0.1

### 3. AI SEO Audit
- **Tool:** https://ai-audit.seogrow.co/
- **Baseline (pre-optimization):** 52/100
- **After round 1:** 80/100
- **Target:** ≥ 85/100
- **Key checks:** meta description, answer target, Q&A headings, HowTo schema, table, llms.txt, About link, citations, author bio, Article schema

### 4. Google Rich Results
- **Tool:** https://search.google.com/test/rich-results
- **Target:** Valid Organization (+ Article, FAQPage, HowTo when present); crawl allowed; no JSON-LD errors

### 5. VWO Deep UX
- **Tool:** https://vwo.com/deep-ux-analysis-report/
- **Target:** Resolve contrast, form, footer, testimonial, and CTA issues; document accepted trade-offs

### 6. Manual smoke test

```bash
cd <project>
npm install
npm run build    # must complete in < 5s
npm run preview  # verify at localhost:4321
```

- [ ] Skip link works
- [ ] All anchor links scroll correctly
- [ ] Contact form shows required markers
- [ ] `/llms.txt` returns markdown with valid links
- [ ] `/sitemap-index.xml` reachable
- [ ] `curl -I https://your-domain.com/` shows security headers

---

## Deployment

```bash
cd <project>
npm run build
npx vercel --prod    # production URL
npx vercel           # preview URL (for pre-merge testing)
```

PageSpeed and external audits require a **live public URL**. Use preview deploys to test before merging to `main`.

---

## Explicit Do-Not-Do List

| Do NOT | Why |
|--------|-----|
| Use Google Fonts CDN | Render-blocking; hurts Performance score |
| Add client-side JS/hydration outside a declared island | Violates static-first; increases TBT |
| Collapse the landing page's FAQ | It is the page's answer target; a FAQ *page* may use native `<details>` |
| Fall back to an empty list when a build-time fetch fails | Publishes an empty page with every check green |
| Enable a data source by the mere presence of an env var | A forgotten var silently removes the pages |
| Inline a script without its hash in the CSP | `script-src 'self'` blocks it silently in production |
| Point an hreflang at a page that is not built | Engines drop the whole language cluster |
| Hard-code a URL in a page | Pass the locale-relative `path`; the layout derives the rest |
| Fabricate hero/product screenshots | Misleading; no real assets |
| Invent author Person schema | E-E-A-T penalty if credentials are fake |
| Create empty cluster pages for audit gaming | Low-quality signal; prefer honest internal anchors |
| Skip `llms.txt` | Fails GEO / AI SEO checks |
| Use `<dt>`/`<dd>` for FAQ questions | Fails AEO Q&A format detection; use `<h3>` |
| Omit security headers | Fails Lighthouse Best Practices |
| Commit `node_modules/`, `dist/`, `.astro/`, `.vercel/` | Build artifacts; not source |

---

## AI Assistant Instructions

When asked to build or optimize a Battoni landing page:

1. **Copy `public.battoni.dev/` as the template** (the canonical, optimized reference build). `rnc/vitrum/` is only the bare Astro bootstrap.
2. **Edit the data files — they are the only source of on-page copy.** `src/data/brand.ts` (name, domain, contact), `src/data/locales/{locale}.ts` (the landing page, chrome included), `src/data/pages/*.ts` (other pages). Strings interpolate `SITE_NAME`, so the brand cascades from one value. Decide the languages in `src/data/i18n.ts` first. Do not hardcode copy back into `.astro` components.
3. **Follow the seven pillars in order:** Performance → UX → UI → SEO → AEO → GEO → Conversion.
4. **Never skip structured data** — Organization, WebPage, Article, FAQPage, HowTo as applicable.
5. **Always add `llms.txt`** following the spec at https://llmstxt.org/.
6. **Cite real sources** with `(Source: name)` for any performance or SEO benchmark claims.
7. **Run the audit checklist** before declaring done.
8. **Document accepted trade-offs** (e.g. brand mono font, no accordion, no fabricated imagery).
9. **Keep zero client JS** except a declared island for a live value, built to the island rules above.
10. **Deploy to preview** and re-run PageSpeed + AI SEO Audit before production.

---

## Reference Implementation Results

Validated on https://public-battoni-dev.vercel.app/ (June 2026):

| Tool | Result |
|------|--------|
| Lighthouse (PageSpeed) | 100 / 100 / 100 / 100 (desktop & mobile) |
| GTmetrix | 100% Performance, 100% Structure, LCP 281ms, TBT 0ms, CLS 0 |
| AI SEO Audit | 52 → 80/100 (round 2 optimizations pending re-audit) |
| Google Rich Results | Valid Organization; crawl successful |
| VWO Deep UX | Targeted fixes applied; brand font retained by design |

---

## External References

| Topic | URL |
|-------|-----|
| Astro | https://docs.astro.build/ |
| Astro Fonts API | https://docs.astro.build/en/guides/fonts/ |
| Core Web Vitals | https://web.dev/vitals/ |
| LCP | https://web.dev/articles/lcp |
| Google SEO Starter Guide | https://developers.google.com/search/docs/fundamentals/seo-starter-guide |
| Structured Data | https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data |
| llms.txt spec | https://llmstxt.org/ |
| PageSpeed Insights | https://pagespeed.web.dev/ |
| GTmetrix | https://gtmetrix.com/ |
| AI SEO Audit | https://ai-audit.seogrow.co/ |
| Google Rich Results Test | https://search.google.com/test/rich-results |
| VWO Deep UX | https://vwo.com/deep-ux-analysis-report/ |
| Schema.org — Article | https://schema.org/Article |
| Schema.org — FAQPage | https://schema.org/FAQPage |
| Schema.org — HowTo | https://schema.org/HowTo |

---

*Maintained by Battoni. Update this recipe when audit targets, stack versions, or pillar requirements change.*
