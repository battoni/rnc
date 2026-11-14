import type { RouteRecordRaw } from 'vue-router';
import { FISCAL_PERMISSIONS, FISCAL_ROUTE_NAMES } from '../../constants';

/* "Crédito de fornecedores" (rnc-2 R21–R22), under the team's group — `fiscal.read` (equipe, gestao; D14). */
const SupplierCreditRoutes: RouteRecordRaw[] = [
  {
    path: '/team/suppliers',
    name: `${FISCAL_ROUTE_NAMES.suppliers}-en`,
    meta: { permission: FISCAL_PERMISSIONS.read },
    component: () => import('./SupplierCreditView.view.vue'),
  },
  {
    path: '/equipe/fornecedores',
    name: `${FISCAL_ROUTE_NAMES.suppliers}-pt-BR`,
    meta: { permission: FISCAL_PERMISSIONS.read },
    component: () => import('./SupplierCreditView.view.vue'),
  },
];

export default SupplierCreditRoutes;
