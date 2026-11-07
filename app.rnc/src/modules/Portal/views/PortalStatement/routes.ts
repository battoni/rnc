import type { RouteRecordRaw } from 'vue-router';
import { PORTAL_PERMISSIONS, PORTAL_ROUTE_NAMES } from '../../constants';

/* The partner's statement (rnc-2 R11), behind the portal key like the inbox. */
const PortalStatementRoutes: RouteRecordRaw[] = [
  {
    path: '/partner/statement',
    name: `${PORTAL_ROUTE_NAMES.statement}-en`,
    meta: { permission: PORTAL_PERMISSIONS.access },
    component: () => import('./PortalStatementView.view.vue'),
  },
  {
    path: '/parceiro/extrato',
    name: `${PORTAL_ROUTE_NAMES.statement}-pt-BR`,
    meta: { permission: PORTAL_PERMISSIONS.access },
    component: () => import('./PortalStatementView.view.vue'),
  },
];

export default PortalStatementRoutes;
