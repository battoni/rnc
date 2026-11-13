import type { RouteRecordRaw } from 'vue-router';
import { PIPELINE_PERMISSIONS, PIPELINE_ROUTE_NAMES } from '../../constants';

/* The board (R18), closed by the key that opens the team's rail group (AM8). */
const PipelineRoutes: RouteRecordRaw[] = [
  {
    path: '/team/pipeline',
    name: `${PIPELINE_ROUTE_NAMES.board}-en`,
    meta: { permission: PIPELINE_PERMISSIONS.read },
    component: () => import('./PipelineView.view.vue'),
  },
  {
    path: '/equipe/pipeline',
    name: `${PIPELINE_ROUTE_NAMES.board}-pt-BR`,
    meta: { permission: PIPELINE_PERMISSIONS.read },
    component: () => import('./PipelineView.view.vue'),
  },
];

export default PipelineRoutes;
