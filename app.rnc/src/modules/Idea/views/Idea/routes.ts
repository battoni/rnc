import type { RouteRecordRaw } from 'vue-router';

const IdeaRoutes: RouteRecordRaw[] = [
  {
    path: '/ideas/:id',
    name: 'idea-en',
    meta: { permission: 'ideas.read' },
    component: () => import('./IdeaView.view.vue'),
  },
  {
    path: '/ideias/:id',
    name: 'idea-pt-BR',
    meta: { permission: 'ideas.read' },
    component: () => import('./IdeaView.view.vue'),
  },
];

export default IdeaRoutes;
