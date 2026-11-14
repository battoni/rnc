import type { RouteRecordRaw } from 'vue-router';
import { firmSiteOnly, SITE_ROUTE_NAMES } from '@SiteModule';

/* The diagnostic takes over the site's CTA destination (F2 W12): the same path and route name. */
const DiagnosticRoutes: RouteRecordRaw[] = [
  {
    path: '/diagnostico',
    name: SITE_ROUTE_NAMES.diagnostic,
    meta: { isOpen: true },
    // rnc-4 R28: the diagnostic never shows at the root — the platform firm's site is the product's.
    beforeEnter: firmSiteOnly,
    component: () => import('./DiagnosticView.view.vue'),
  },
];

export default DiagnosticRoutes;
