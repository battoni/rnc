import type { RouteRecordRaw } from 'vue-router';

const ComponentsRoutes: RouteRecordRaw[] = [
  {
    path: '/components',
    name: 'components-en',
    component: () => import('./ComponentsView.view.vue'),
    meta: { permission: 'design.read' },
  },
  {
    path: '/componentes',
    name: 'components-pt-BR',
    component: () => import('./ComponentsView.view.vue'),
    meta: { permission: 'design.read' },
  },
];

export default ComponentsRoutes;
