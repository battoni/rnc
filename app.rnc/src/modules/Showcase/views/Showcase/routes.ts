import type { RouteRecordRaw } from 'vue-router';

const ShowcaseRoutes: RouteRecordRaw[] = [
  {
    path: '/showcase',
    name: 'showcase-en',
    component: () => import('./ShowcaseView.view.vue'),
    meta: { permission: 'design.read' },
  },
  {
    path: '/vitrine',
    name: 'showcase-pt-BR',
    component: () => import('./ShowcaseView.view.vue'),
    meta: { permission: 'design.read' },
  },
];

export default ShowcaseRoutes;
