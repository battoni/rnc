import type { RouteRecordRaw } from 'vue-router';

const FeaturesRoutes: RouteRecordRaw[] = [
  {
    path: '/features',
    name: 'features-en',
    component: () => import('./FeaturesView.view.vue'),
    meta: { permission: 'design.read' },
  },
  {
    path: '/funcionalidades',
    name: 'features-pt-BR',
    component: () => import('./FeaturesView.view.vue'),
    meta: { permission: 'design.read' },
  },
];

export default FeaturesRoutes;
