import type { RouteRecordRaw } from 'vue-router';
import { CONTRACTS_PERMISSIONS, CONTRACTS_ROUTE_NAMES } from '../../constants';

/* "Alerta de contratos" (rnc-3 R18–R22), under Reinaldo's group — `contracts.manage` (gestao, operador). */
const ContractsRoutes: RouteRecordRaw[] = [
  {
    path: '/management/contracts',
    name: `${CONTRACTS_ROUTE_NAMES.alert}-en`,
    meta: { permission: CONTRACTS_PERMISSIONS.manage },
    component: () => import('./ContractsView.view.vue'),
  },
  {
    path: '/gestao/contratos',
    name: `${CONTRACTS_ROUTE_NAMES.alert}-pt-BR`,
    meta: { permission: CONTRACTS_PERMISSIONS.manage },
    component: () => import('./ContractsView.view.vue'),
  },
];

export default ContractsRoutes;
