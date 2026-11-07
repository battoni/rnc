import type { RouteRecordRaw } from 'vue-router';
import { PARTNER_PERMISSIONS, PARTNER_ROUTE_NAMES } from '../../constants';

/* The trail (R23), under Reinaldo's group — the team never reaches it (AM8). */
const TrailRoutes: RouteRecordRaw[] = [
  {
    path: '/ceo/trail',
    name: `${PARTNER_ROUTE_NAMES.trail}-en`,
    meta: { permission: PARTNER_PERMISSIONS.trail },
    component: () => import('./TrailView.view.vue'),
  },
  {
    path: '/reinaldo/trilha',
    name: `${PARTNER_ROUTE_NAMES.trail}-pt-BR`,
    meta: { permission: PARTNER_PERMISSIONS.trail },
    component: () => import('./TrailView.view.vue'),
  },
];

export default TrailRoutes;
