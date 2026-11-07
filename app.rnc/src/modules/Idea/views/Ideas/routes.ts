import type { RouteRecordRaw } from 'vue-router';

/*
 * The board. `meta.permission` closes the route; the nav filter only hides it, and the API
 * enforces the same key — a guard in the browser is a courtesy, never a boundary.
 */
const IdeasRoutes: RouteRecordRaw[] = [
  {
    path: '/ideas',
    name: 'ideas-en',
    meta: { permission: 'ideas.read' },
    component: () => import('./IdeasView.view.vue'),
  },
  {
    path: '/ideias',
    name: 'ideas-pt-BR',
    meta: { permission: 'ideas.read' },
    component: () => import('./IdeasView.view.vue'),
  },
];

export default IdeasRoutes;
