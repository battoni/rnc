import type { RouteRecordRaw } from 'vue-router';

/*
 * `meta.permission` closes the route; the nav filter only hides the link, and the API refuses the
 * list without the same key. Without it, typing `/tips` opened a screen that could only fail.
 */
const TipRoutes: RouteRecordRaw[] = [
  {
    path: '/tips',
    name: 'tips-en',
    meta: { permission: 'tips.read' },
    component: () => import('./views/Tips/TipsView.view.vue'),
  },
  {
    path: '/dicas',
    name: 'tips-pt-BR',
    meta: { permission: 'tips.read' },
    component: () => import('./views/Tips/TipsView.view.vue'),
  },
];

export default TipRoutes;
