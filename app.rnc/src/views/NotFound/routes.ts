import type { RouteRecordRaw } from 'vue-router';

/*
 * The catch-all. vue-router 4 ranks a path by how specific it is, and `/:pathMatch(.*)*` ranks below
 * every real route wherever it is declared; it is listed last only so the router file reads in order.
 *
 * `isOpen`, not `isPublic`. A wrong address is wrong whether or not you are signed in, and the two
 * flags the guard knew were both wrong for it: a private route sends a signed-out visitor to the
 * login screen to authenticate for a page that does not exist, and a public one sends a signed-in
 * person HOME — the typo is silently replaced by a different page and nobody is told.
 */
const NotFoundRoutes: RouteRecordRaw[] = [
  {
    path: '/:pathMatch(.*)*',
    name: 'not-found',
    meta: { isOpen: true },
    component: () => import('./NotFoundView.view.vue'),
  },
];

export default NotFoundRoutes;
