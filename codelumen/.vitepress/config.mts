import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { defineConfig } from 'vitepress'
import { withMermaid } from 'vitepress-plugin-mermaid'

// Sidebar data for the rule pages is generated from `.claude/rules/` by
// `scripts/generate-rules.mjs` (runs via the predocs:* npm scripts).
const rulesSidebar = JSON.parse(
  readFileSync(fileURLToPath(new URL('./generated/rules-sidebar.json', import.meta.url)), 'utf8')
) as Record<string, { text: string; link: string }[]>

// One page per command, generated from `.claude/commands/`.
const commandsSidebar = JSON.parse(
  readFileSync(fileURLToPath(new URL('./generated/commands-sidebar.json', import.meta.url)), 'utf8')
) as { text: string; link: string }[]

// https://vitepress.dev/reference/site-config
export default withMermaid(defineConfig({
  title: "Code Lumen",
  description: "Battoni Dev internal handbook",

  // Theme appearance configuration
  appearance: true, // Enable light/dark mode toggle

  themeConfig: {
    // Logo configuration
    logo: {
      light: '/logo/logo-light-icon.svg',
      dark: '/logo/logo-dark-icon.svg',
      alt: 'Code Lumen'
    },

    // https://vitepress.dev/reference/default-theme-config
    nav: [
      { text: 'Home', link: '/' },
      { text: 'Monorepo', link: '/monorepo' },
      { text: 'Workflow', link: '/git-flow' },
      { text: 'AI Tooling', link: '/ai-tooling' }
    ],

    sidebar: [
      {
        text: 'Start here',
        items: [
          { text: 'About', link: '/about' },
          { text: 'The rnc Monorepo', link: '/monorepo' },
          { text: 'Repositories', link: '/repositories' },
          { text: 'AI Tooling', link: '/ai-tooling' },
          { text: 'Access Control', link: '/access-control' },
        ],
      },
      {
        text: 'Workflow',
        items: [
          { text: 'Git/Notion Flow', link: '/git-flow' },
          { text: 'Day to Day', link: '/day-to-day' },
          ...rulesSidebar.shared ?? [],
        ],
      },
      {
        text: 'Projects',
        items: [
          { text: 'Arcus', link: '/projects/api.rnc', collapsed: true, items: rulesSidebar.api.rnc ?? [] },
          { text: 'app.rnc', link: '/projects/app.rnc', collapsed: true, items: rulesSidebar.app.rnc ?? [] },
          { text: 'Vitrum', link: '/projects/vitrum' },
          { text: 'Liquen', link: '/projects/liquen' },
          { text: 'Cortex', link: '/projects/cortex', collapsed: true, items: commandsSidebar },
          { text: 'Codelumen', link: '/projects/codelumen' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Design System', link: '/design-system' },
          { text: 'Technologies', link: '/technologies' },
        ],
      },
      {
        text: 'Recipes and Snippets',
        items: [
          { text: 'Intro', link: '/intro-recipes-snippets' },
          { text: 'Landing Pages', link: '/landing-page-recipe' },
          { text: 'Icons', link: '/icons' },
          { text: 'Tooltips', link: '/tooltips' },
        ],
      },
    ],

    socialLinks: [
      { icon: 'github', link: 'https://github.com/battoni/codelumen' }
    ]
  },

  // Import Google Fonts and favicon
  head: [
    ['link', { rel: 'preconnect', href: 'https://fonts.googleapis.com' }],
    ['link', { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' }],
    ['link', { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Sometype+Mono:ital,wght@0,400..700;1,400..700&family=Space+Grotesk:wght@300..700&display=swap' }],

    // Favicon
    ['link', { rel: 'icon', type: 'image/svg+xml', href: '/logo/logo-light-icon.svg' }],
    ['link', { rel: 'icon', type: 'image/png', href: '/logo/logo-light-icon.png' }],
    ['link', { rel: 'apple-touch-icon', href: '/logo/logo-light-icon.png' }],
    ['meta', { name: 'theme-color', content: '#C0E021' }]
  ]
}))
