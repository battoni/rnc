import type { RouteRecordRaw } from 'vue-router';
import { PARTNER_PERMISSIONS, PARTNER_ROUTE_NAMES } from '../../constants';

/* The partner registry (R21), under Reinaldo's group. */
const PartnersRoutes: RouteRecordRaw[] = [
  {
    path: '/ceo/partners',
    name: `${PARTNER_ROUTE_NAMES.partners}-en`,
    meta: { permission: PARTNER_PERMISSIONS.manage },
    component: () => import('./PartnersView.view.vue'),
  },
  {
    path: '/reinaldo/parceiros',
    name: `${PARTNER_ROUTE_NAMES.partners}-pt-BR`,
    meta: { permission: PARTNER_PERMISSIONS.manage },
    component: () => import('./PartnersView.view.vue'),
  },
];

export default PartnersRoutes;
