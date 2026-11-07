import type { RouteRecordRaw } from 'vue-router';
import { FISCAL_PERMISSIONS, FISCAL_ROUTE_NAMES } from '../../constants';

/* "Notas fiscais" (rnc-2 R15, R17), under Reinaldo's group — `fiscal.import` (ceo, operador; D14). */
const NfeImportRoutes: RouteRecordRaw[] = [
  {
    path: '/ceo/invoices',
    name: `${FISCAL_ROUTE_NAMES.imports}-en`,
    meta: { permission: FISCAL_PERMISSIONS.import },
    component: () => import('./NfeImportView.view.vue'),
  },
  {
    path: '/reinaldo/notas',
    name: `${FISCAL_ROUTE_NAMES.imports}-pt-BR`,
    meta: { permission: FISCAL_PERMISSIONS.import },
    component: () => import('./NfeImportView.view.vue'),
  },
];

export default NfeImportRoutes;
