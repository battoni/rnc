import type { RouteRecordRaw } from 'vue-router';
import { METRICS_PERMISSIONS, METRICS_ROUTE_NAMES } from '../../constants';

/* Reinaldo's funnel (R24), the first child of his rail group — the team never reaches it (AM8). */
const MetricsRoutes: RouteRecordRaw[] = [
  {
    path: '/ceo/metrics',
    name: `${METRICS_ROUTE_NAMES.funnel}-en`,
    meta: { permission: METRICS_PERMISSIONS.read },
    component: () => import('./MetricsView.view.vue'),
  },
  {
    path: '/reinaldo/metricas',
    name: `${METRICS_ROUTE_NAMES.funnel}-pt-BR`,
    meta: { permission: METRICS_PERMISSIONS.read },
    component: () => import('./MetricsView.view.vue'),
  },
];

export default MetricsRoutes;
