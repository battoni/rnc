import type { RouteRecordRaw } from 'vue-router';
import { SITE_ROUTE_NAMES } from '../../constants';

/*
 * The firm's privacy notice (rnc-4 R38, F9b), open to everyone like the rest of the site (AM15). It
 * answers at the root too — there the platform is the controller of what the beta form keeps (W106).
 */
const PrivacyRoutes: RouteRecordRaw[] = [
  {
    path: '/privacidade',
    name: SITE_ROUTE_NAMES.privacy,
    meta: { isOpen: true },
    component: () => import('./PrivacyView.view.vue'),
  },
];

export default PrivacyRoutes;
