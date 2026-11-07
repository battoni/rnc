import type { RouteRecordRaw } from 'vue-router';

/*
 * `meta.permission` closes the route; the nav filter only hides the link, and the API refuses the
 * list without the same key. Without it, typing the address opened a screen that could only fail.
 */
const UsersRoutes: RouteRecordRaw[] = [
  {
    path: '/users',
    name: 'users-en',
    meta: { permission: 'users.read' },
    component: () => import('./UsersView.view.vue'),
  },
  {
    path: '/usuarios',
    name: 'users-pt-BR',
    meta: { permission: 'users.read' },
    component: () => import('./UsersView.view.vue'),
  },
  {
    path: '/usuários',
    redirect: '/usuarios',
  },
];

export default UsersRoutes;
