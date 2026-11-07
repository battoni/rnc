import { readFileSync } from 'node:fs';
import { defineConfig } from 'vitepress';
import { withMermaid } from 'vitepress-plugin-mermaid';
import container from 'markdown-it-container';
import { SIDEBAR } from './sidebar.ts';

// The portal's version is package.json's. The PDF export prints it on the cover
// and in the footer, so a printed copy can be told apart from the live site —
// bump it when the user-facing pages change in a material way.
const docsVersion: string = JSON.parse(
  readFileSync(new URL('../package.json', import.meta.url), 'utf-8'),
).version;

// Anti-FOUC: apply the saved User/Dev mode to <html> before first paint, so the
// toggle never flashes the wrong content on load. Keep this in sync with the
// STORAGE_KEY in theme/mode.ts.
const modeBootScript = `;(function(){try{var m=localStorage.getItem('{{SLUG}}-doc-mode');document.documentElement.classList.add('mode-'+(m==='dev'?'dev':'user'));}catch(e){document.documentElement.classList.add('mode-user');}})();`;

export default withMermaid(
  defineConfig({
    lang: 'en',
    title: '{{TITLE}}',
    description: '{{DESCRIPTION}}',
    cleanUrls: true,
    lastUpdated: true,
    // The second anti-indexing layer. robots.txt asks a crawler not to fetch; the X-Robots-Tag in
    // vercel.json and this meta tell one that fetched anyway not to keep the page — and the meta
    // travels with the HTML to a host that sends no headers. Keep the two values identical, and
    // remove both (with public/robots.txt) only for a portal that is meant to be found.
    head: [
      ['meta', { name: 'robots', content: 'noindex, nofollow, noarchive, nosnippet, noimageindex' }],
      ['script', {}, modeBootScript],
    ],

    vite: {
      define: {
        __DOCS_VERSION__: JSON.stringify(docsVersion),
      },
    },

    markdown: {
      // `::: dev … :::` → <div class="dev-only"> (visible only in Developer mode);
      // `::: user … :::` → <div class="user-only"> (visible only in User mode).
      config(md) {
        md.use(container, 'dev', {
          render(tokens: { nesting: number }[], idx: number) {
            return tokens[idx].nesting === 1 ? '<div class="dev-only">\n' : '</div>\n';
          },
        });
        md.use(container, 'user', {
          render(tokens: { nesting: number }[], idx: number) {
            return tokens[idx].nesting === 1 ? '<div class="user-only">\n' : '</div>\n';
          },
        });
      },
    },

    themeConfig: {
      logo: '/logo.svg',
      search: { provider: 'local' },

      // Top shortcuts jump to each area; the full sidebar (below) is the same on
      // every page so nobody gets lost between sections. EDIT for your project.
      nav: [
        { text: 'Home', link: '/' },
        { text: 'Guide', link: '/guide/getting-started' },
        { text: 'Concepts', link: '/concepts/overview' },
        { text: 'Reference', link: '/reference/architecture' },
      ],

      // ONE unified sidebar, shown on all pages — defined in ./sidebar.ts
      // (the PDF export reads the same list).
      sidebar: SIDEBAR,

      outline: { level: [2, 3], label: 'On this page' },
      docFooter: { prev: 'Previous', next: 'Next' },
    },
  }),
);
