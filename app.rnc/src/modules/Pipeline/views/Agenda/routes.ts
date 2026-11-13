import type { RouteRecordRaw } from 'vue-router';
import { PIPELINE_PERMISSIONS, PIPELINE_ROUTE_NAMES } from '../../constants';

/* The agenda week (R20), behind `agenda.read` (AM8). */
const AgendaRoutes: RouteRecordRaw[] = [
  {
    path: '/team/agenda',
    name: `${PIPELINE_ROUTE_NAMES.agenda}-en`,
    meta: { permission: PIPELINE_PERMISSIONS.agenda },
    component: () => import('./AgendaView.view.vue'),
  },
  {
    path: '/equipe/agenda',
    name: `${PIPELINE_ROUTE_NAMES.agenda}-pt-BR`,
    meta: { permission: PIPELINE_PERMISSIONS.agenda },
    component: () => import('./AgendaView.view.vue'),
  },
];

export default AgendaRoutes;
