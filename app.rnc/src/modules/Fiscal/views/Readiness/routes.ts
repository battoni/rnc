import type { RouteRecordRaw } from 'vue-router';
import { FISCAL_PERMISSIONS, FISCAL_ROUTE_NAMES } from '../../constants';

/* "Prontidão da NF-e" (rnc-2 R18–R20), under the team's group — `fiscal.read` (equipe, gestao; D14). */
const ReadinessRoutes: RouteRecordRaw[] = [
  {
    path: '/team/invoices',
    name: `${FISCAL_ROUTE_NAMES.readiness}-en`,
    meta: { permission: FISCAL_PERMISSIONS.read },
    component: () => import('./ReadinessView.view.vue'),
  },
  {
    path: '/equipe/nfe',
    name: `${FISCAL_ROUTE_NAMES.readiness}-pt-BR`,
    meta: { permission: FISCAL_PERMISSIONS.read },
    component: () => import('./ReadinessView.view.vue'),
  },
];

export default ReadinessRoutes;
