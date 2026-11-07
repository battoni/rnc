import type { RouteRecordRaw } from 'vue-router';
import { PORTAL_PERMISSIONS, PORTAL_ROUTE_NAMES } from '../../constants';

/* The partner's portal (rnc-2 R3): its landing, behind the portal key. */
const PortalHomeRoutes: RouteRecordRaw[] = [
  {
    path: '/partner',
    name: `${PORTAL_ROUTE_NAMES.home}-en`,
    meta: { permission: PORTAL_PERMISSIONS.access },
    component: () => import('./PortalHomeView.view.vue'),
  },
  {
    path: '/parceiro',
    name: `${PORTAL_ROUTE_NAMES.home}-pt-BR`,
    meta: { permission: PORTAL_PERMISSIONS.access },
    component: () => import('./PortalHomeView.view.vue'),
  },
];

export default PortalHomeRoutes;
