import type { DefaultTheme } from 'vitepress';

// ONE unified sidebar, shown on all pages. EDIT for your project. Keep the
// developer reference group `collapsed: true`. For a CLIENT HANDOFF, add a
// "★ For you" group first (highlights / feedback / hiring) — see GUIDE.md.
//
// It lives in its own module because two things read it: `config.mts` (the
// site) and `theme/pdf/userDocPages.ts` (the PDF export, which prints every
// page listed here except the developer reference, in this order).
const SIDEBAR: DefaultTheme.SidebarItem[] = [
  {
    text: 'Guide',
    collapsed: false,
    items: [
      { text: 'First steps', link: '/guide/getting-started' },
      { text: 'Finding your way around', link: '/guide/navigation' },
    ],
  },
  {
    text: 'Concepts',
    collapsed: false,
    items: [{ text: 'System overview', link: '/concepts/overview' }],
  },
  {
    text: 'Operations',
    collapsed: false,
    items: [{ text: 'Deployment', link: '/operations/deployment' }],
  },
  {
    text: 'Developer reference',
    collapsed: true,
    items: [
      { text: 'Architecture', link: '/reference/architecture' },
      { text: 'Database schema', link: '/reference/database-schema' },
      { text: 'API endpoints', link: '/reference/api-endpoints' },
      { text: 'Views & navigation', link: '/reference/views-navigation' },
    ],
  },
];

export { SIDEBAR };
