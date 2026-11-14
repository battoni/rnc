import type { RouteRecordRaw } from 'vue-router';

/*
 * `meta.permission` closes the route; the nav filter only hides the link. Without it, typing the
 * address opened the permissions screen for any signed-in account. `permissions.read` is the key
 * the navigation already asks for — the API also reads roles for `users.create`/`users.update`
 * (the user form needs the list), but this screen is where permissions are read and changed.
 *
 * `meta.role`: the operator's alone (rnc-4 verify). The API keeps every role write to the operator (403
 * `platform.operatorOnly`) and roles are global, so a firm account granted `permissions.*` would open a screen
 * whose every save fails — `attachAuthGuard` sends anyone else home.
 */
const OPERATOR_ROLE_SLUG = 'operador';

const RolesAndPermissionsRoutes: RouteRecordRaw[] = [
  {
    path: '/roles-and-permissions',
    name: 'roles-and-permissions-en',
    meta: { permission: 'permissions.read', role: OPERATOR_ROLE_SLUG },
    component: () => import('./RolesAndPermissionsView.view.vue'),
  },
  {
    path: '/permissoes-e-perfis',
    name: 'roles-and-permissions-pt-BR',
    meta: { permission: 'permissions.read', role: OPERATOR_ROLE_SLUG },
    component: () => import('./RolesAndPermissionsView.view.vue'),
  },
  {
    path: '/permissões-e-perfis',
    redirect: '/permissoes-e-perfis',
  },
];

export default RolesAndPermissionsRoutes;
