import type { RouteRecordRaw } from 'vue-router';

/*
 * Novidades is gated on `ideas.read`, not on a key of its own: reading the changelog is part of
 * using the product, and writing it reuses `ideas.manage` inside the view.
 */
const NewsRoutes: RouteRecordRaw[] = [
  {
    path: '/news',
    name: 'news-en',
    meta: { permission: 'ideas.read' },
    component: () => import('./NewsView.view.vue'),
  },
  {
    path: '/novidades',
    name: 'news-pt-BR',
    meta: { permission: 'ideas.read' },
    component: () => import('./NewsView.view.vue'),
  },
];

export default NewsRoutes;
