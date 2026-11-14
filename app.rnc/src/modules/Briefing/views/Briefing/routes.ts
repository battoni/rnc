import type { RouteRecordRaw } from 'vue-router';
import { BRIEFING_PERMISSIONS, BRIEFING_ROUTE_NAMES } from '../../constants';

/* "Briefing do dia" (rnc-3 R26), Reinaldo's landing — `briefing.read` (gestao, operador). `/gestao` redirects here (Home's Workspace routes). */
const BriefingRoutes: RouteRecordRaw[] = [
  {
    path: '/management/briefing',
    name: `${BRIEFING_ROUTE_NAMES.briefing}-en`,
    meta: { permission: BRIEFING_PERMISSIONS.read },
    component: () => import('./BriefingView.view.vue'),
  },
  {
    path: '/gestao/briefing',
    name: `${BRIEFING_ROUTE_NAMES.briefing}-pt-BR`,
    meta: { permission: BRIEFING_PERMISSIONS.read },
    component: () => import('./BriefingView.view.vue'),
  },
];

export default BriefingRoutes;
