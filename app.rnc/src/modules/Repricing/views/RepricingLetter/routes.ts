import type { RouteRecordRaw } from 'vue-router';
import { REPRICING_PERMISSIONS, REPRICING_ROUTE_NAMES } from '../../constants';

/* One client's letter of repricing (R26): a page of its own, without the back office's chrome, so it prints clean. */
const RepricingLetterRoutes: RouteRecordRaw[] = [
  {
    path: '/ceo/repricing/:id/letter',
    name: `${REPRICING_ROUTE_NAMES.letter}-en`,
    meta: { permission: REPRICING_PERMISSIONS.manage },
    component: () => import('./RepricingLetterView.view.vue'),
  },
  {
    path: '/reinaldo/reajuste/:id/carta',
    name: `${REPRICING_ROUTE_NAMES.letter}-pt-BR`,
    meta: { permission: REPRICING_PERMISSIONS.manage },
    component: () => import('./RepricingLetterView.view.vue'),
  },
];

export default RepricingLetterRoutes;
