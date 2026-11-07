import type { RouteRecordRaw } from 'vue-router';
import { SITE_ROUTE_NAMES } from '../../constants';

/* The CTA's destination (W12): a placeholder until F3's diagnostic takes the path over. */
const DiagnosticRoutes: RouteRecordRaw[] = [
  {
    path: '/diagnostico',
    name: SITE_ROUTE_NAMES.diagnostic,
    meta: { isOpen: true },
    component: () => import('./DiagnosticView.view.vue'),
  },
];

export default DiagnosticRoutes;
