import type { RouteRecordRaw } from 'vue-router';
import { REPRICING_PERMISSIONS, REPRICING_ROUTE_NAMES } from '../../constants';

/* Fee repricing (R26), under Reinaldo's group — the team never reaches it (AM8). */
const RepricingRoutes: RouteRecordRaw[] = [
  {
    path: '/management/repricing',
    name: `${REPRICING_ROUTE_NAMES.list}-en`,
    meta: { permission: REPRICING_PERMISSIONS.manage },
    component: () => import('./RepricingView.view.vue'),
  },
  {
    path: '/gestao/reajuste',
    name: `${REPRICING_ROUTE_NAMES.list}-pt-BR`,
    meta: { permission: REPRICING_PERMISSIONS.manage },
    component: () => import('./RepricingView.view.vue'),
  },
];

export default RepricingRoutes;
