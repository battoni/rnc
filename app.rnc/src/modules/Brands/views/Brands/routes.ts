import type { RouteRecordRaw } from 'vue-router';
import { BRANDS_PERMISSIONS, BRANDS_ROUTE_NAMES } from '../../constants';

/* The brands console (rnc-3 R5), under the operator's group. */
const BrandsRoutes: RouteRecordRaw[] = [
  {
    path: '/operator/brands',
    name: `${BRANDS_ROUTE_NAMES.list}-en`,
    meta: { permission: BRANDS_PERMISSIONS.manage },
    component: () => import('./BrandsView.view.vue'),
  },
  {
    path: '/operador/marcas',
    name: `${BRANDS_ROUTE_NAMES.list}-pt-BR`,
    meta: { permission: BRANDS_PERMISSIONS.manage },
    component: () => import('./BrandsView.view.vue'),
  },
];

export default BrandsRoutes;
