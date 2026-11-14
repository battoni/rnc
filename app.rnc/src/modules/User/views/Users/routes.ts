import type { RouteRecordRaw } from 'vue-router';

/*
 * `meta.permission` closes the route; the nav filter only hides the link, and the API refuses the
 * list without the same key. Without it, typing the address opened a screen that could only fail.
 *
 * `meta.role`: the operator's alone (Chrome QA 5.2). The firm manager holds `users.read` since rnc-4 F5a for
 * its team's logins, which it runs at /gestao/equipe over the same endpoints, scoped to its firm; this
 * bootstrap screen is the operator's group's, so `attachAuthGuard` sends anyone else home, as the rail hides it.
 */
const UsersRoutes: RouteRecordRaw[] = [
  {
    path: '/users',
    name: 'users-en',
    meta: { permission: 'users.read', role: 'operador' },
    component: () => import('./UsersView.view.vue'),
  },
  {
    path: '/usuarios',
    name: 'users-pt-BR',
    meta: { permission: 'users.read', role: 'operador' },
    component: () => import('./UsersView.view.vue'),
  },
  {
    path: '/usuários',
    redirect: '/usuarios',
  },
];

export default UsersRoutes;
