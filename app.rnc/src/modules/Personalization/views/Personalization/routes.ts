import type { RouteRecordRaw } from 'vue-router';

const PersonalizationRoutes: RouteRecordRaw[] = [
  {
    path: '/personalization',
    name: 'personalization-en',
    component: () => import('./PersonalizationView.view.vue'),
  },
  {
    path: '/personalizacao',
    name: 'personalization-pt-BR',
    component: () => import('./PersonalizationView.view.vue'),
  },
];

export default PersonalizationRoutes;
