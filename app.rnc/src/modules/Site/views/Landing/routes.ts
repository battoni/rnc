import type { RouteRecordRaw } from 'vue-router';
import { loadHomeView } from '../../composables/productSite';
import { SITE_ROUTE_NAMES } from '../../constants';

/*
 * The public site (rnc-1 D4, AM15). `isOpen` is the router's "answers everyone, signed in or not"
 * (`routeAccess.helper`): a visitor is never sent to the login screen, and a member of the team who
 * opens the site is not bounced to the back office the way `isPublic` (signed-out only) would.
 */
const LandingRoutes: RouteRecordRaw[] = [
  {
    path: '/',
    name: SITE_ROUTE_NAMES.home,
    meta: { isOpen: true },
    // rnc-4 R28: the product's landing on the platform firm (the root), the firm's own everywhere else.
    component: loadHomeView,
  },
];

export default LandingRoutes;
