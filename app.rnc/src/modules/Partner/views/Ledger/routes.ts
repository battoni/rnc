import type { RouteRecordRaw } from 'vue-router';
import { PARTNER_PERMISSIONS, PARTNER_ROUTE_NAMES } from '../../constants';

/* The network ledger (rnc-2 R11), under Reinaldo's group — `ledger.read` (ceo, operador; D14). */
const LedgerRoutes: RouteRecordRaw[] = [
  {
    path: '/ceo/ledger',
    name: `${PARTNER_ROUTE_NAMES.ledger}-en`,
    meta: { permission: PARTNER_PERMISSIONS.ledger },
    component: () => import('./LedgerView.view.vue'),
  },
  {
    path: '/reinaldo/extrato',
    name: `${PARTNER_ROUTE_NAMES.ledger}-pt-BR`,
    meta: { permission: PARTNER_PERMISSIONS.ledger },
    component: () => import('./LedgerView.view.vue'),
  },
];

export default LedgerRoutes;
