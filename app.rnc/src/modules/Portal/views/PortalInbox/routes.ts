import type { RouteRecordRaw } from 'vue-router';
import { PORTAL_PERMISSIONS, PORTAL_ROUTE_NAMES } from '../../constants';

/* The partner's portal (rnc-2 R3, R5): its inbox, behind the portal key. */
const PortalInboxRoutes: RouteRecordRaw[] = [
  {
    path: '/partner',
    name: `${PORTAL_ROUTE_NAMES.home}-en`,
    meta: { permission: PORTAL_PERMISSIONS.access },
    component: () => import('./PortalInboxView.view.vue'),
  },
  {
    path: '/parceiro',
    name: `${PORTAL_ROUTE_NAMES.home}-pt-BR`,
    meta: { permission: PORTAL_PERMISSIONS.access },
    component: () => import('./PortalInboxView.view.vue'),
  },
];

export default PortalInboxRoutes;
