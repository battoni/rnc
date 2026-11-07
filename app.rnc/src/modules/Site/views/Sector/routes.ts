import type { RouteRecordRaw } from 'vue-router';
import { SITE_ROUTE_NAMES } from '../../constants';

/* The six sector pages (rnc-1 R6), open to everyone like the rest of the site (AM15, F1 W4). */
const SectorRoutes: RouteRecordRaw[] = [
  {
    path: '/reforma/:slug',
    name: SITE_ROUTE_NAMES.sector,
    meta: { isOpen: true },
    component: () => import('./SectorView.view.vue'),
  },
];

export default SectorRoutes;
