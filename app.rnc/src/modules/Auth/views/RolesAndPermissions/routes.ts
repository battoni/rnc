import type { RouteRecordRaw } from 'vue-router';

/*
 * `meta.permission` closes the route; the nav filter only hides the link. Without it, typing the
 * address opened the permissions screen for any signed-in account. `permissions.read` is the key
 * the navigation already asks for — the API also reads roles for `users.create`/`users.update`
 * (the user form needs the list), but this screen is where permissions are read and changed.
 */
const RolesAndPermissionsRoutes: RouteRecordRaw[] = [
  {
    path: '/roles-and-permissions',
    name: 'roles-and-permissions-en',
    meta: { permission: 'permissions.read' },
    component: () => import('./RolesAndPermissionsView.view.vue'),
  },
  {
    path: '/permissoes-e-perfis',
    name: 'roles-and-permissions-pt-BR',
    meta: { permission: 'permissions.read' },
    component: () => import('./RolesAndPermissionsView.view.vue'),
  },
  {
    path: '/permissões-e-perfis',
    redirect: '/permissoes-e-perfis',
  },
];

export default RolesAndPermissionsRoutes;
