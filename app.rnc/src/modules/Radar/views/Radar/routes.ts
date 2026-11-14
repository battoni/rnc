import type { RouteRecordRaw } from 'vue-router';
import { RADAR_PERMISSIONS, RADAR_ROUTE_NAMES } from '../../constants';

/* "Radar da carteira" (rnc-2 R24–R27), under Reinaldo's group — `radar.manage` (gestao, operador; D14). */
const RadarRoutes: RouteRecordRaw[] = [
  {
    path: '/management/radar',
    name: `${RADAR_ROUTE_NAMES.radar}-en`,
    meta: { permission: RADAR_PERMISSIONS.manage },
    component: () => import('./RadarView.view.vue'),
  },
  {
    path: '/gestao/radar',
    name: `${RADAR_ROUTE_NAMES.radar}-pt-BR`,
    meta: { permission: RADAR_PERMISSIONS.manage },
    component: () => import('./RadarView.view.vue'),
  },
];

export default RadarRoutes;
