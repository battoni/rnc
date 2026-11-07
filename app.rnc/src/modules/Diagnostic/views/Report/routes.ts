import type { RouteRecordRaw } from 'vue-router';
import { DIAGNOSTIC_ROUTE_NAMES } from '../../constants';

/* The "Relatório de Impacto" the confirmation e-mail links — open, by its own token (AM4, AM15 via `meta.isOpen`, W4). */
const ReportRoutes: RouteRecordRaw[] = [
  {
    path: '/relatorio/:token',
    name: DIAGNOSTIC_ROUTE_NAMES.report,
    meta: { isOpen: true },
    component: () => import('./ReportView.view.vue'),
  },
];

export default ReportRoutes;
