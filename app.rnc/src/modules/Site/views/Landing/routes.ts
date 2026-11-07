import type { RouteRecordRaw } from 'vue-router';

/*
 * The public site (rnc-1 D4, AM15). `isOpen` is the router's "answers everyone, signed in or not"
 * (`routeAccess.helper`): a visitor is never sent to the login screen, and a member of the team who
 * opens the site is not bounced to the back office the way `isPublic` (signed-out only) would.
 */
const LandingRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'site-home',
    meta: { isOpen: true },
    component: () => import('./LandingView.view.vue'),
  },
];

export default LandingRoutes;
