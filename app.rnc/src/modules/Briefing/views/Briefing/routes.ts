import type { RouteRecordRaw } from 'vue-router';
import { BRIEFING_PERMISSIONS, BRIEFING_ROUTE_NAMES } from '../../constants';

/* "Briefing do dia" (rnc-3 R26), Reinaldo's landing — `briefing.read` (ceo, operador). `/reinaldo` redirects here (Home's Workspace routes). */
const BriefingRoutes: RouteRecordRaw[] = [
  {
    path: '/ceo/briefing',
    name: `${BRIEFING_ROUTE_NAMES.briefing}-en`,
    meta: { permission: BRIEFING_PERMISSIONS.read },
    component: () => import('./BriefingView.view.vue'),
  },
  {
    path: '/reinaldo/briefing',
    name: `${BRIEFING_ROUTE_NAMES.briefing}-pt-BR`,
    meta: { permission: BRIEFING_PERMISSIONS.read },
    component: () => import('./BriefingView.view.vue'),
  },
];

export default BriefingRoutes;
