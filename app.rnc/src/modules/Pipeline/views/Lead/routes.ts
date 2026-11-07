import type { RouteRecordRaw } from 'vue-router';
import { PIPELINE_PERMISSIONS, PIPELINE_ROUTE_NAMES } from '../../constants';

/* The lead detail (R19), by its numeric id — read with `toRouteId` in the view. */
const LeadRoutes: RouteRecordRaw[] = [
  {
    path: '/team/leads/:id',
    name: `${PIPELINE_ROUTE_NAMES.lead}-en`,
    meta: { permission: PIPELINE_PERMISSIONS.read },
    component: () => import('./LeadView.view.vue'),
  },
  {
    path: '/equipe/leads/:id',
    name: `${PIPELINE_ROUTE_NAMES.lead}-pt-BR`,
    meta: { permission: PIPELINE_PERMISSIONS.read },
    component: () => import('./LeadView.view.vue'),
  },
];

export default LeadRoutes;
