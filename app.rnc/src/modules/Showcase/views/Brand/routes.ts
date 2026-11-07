import type { RouteRecordRaw } from 'vue-router';

const BrandRoutes: RouteRecordRaw[] = [
  {
    path: '/brand',
    name: 'brand-en',
    component: () => import('./BrandView.view.vue'),
    meta: { permission: 'design.read' },
  },
  {
    path: '/marca',
    name: 'brand-pt-BR',
    component: () => import('./BrandView.view.vue'),
    meta: { permission: 'design.read' },
  },
];

export default BrandRoutes;
