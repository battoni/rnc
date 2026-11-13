import type { RouteRecordRaw } from 'vue-router';
import { DiagnosticRoutes, ReportRoutes } from './views';

/* The public diagnostic and its report — open, under the site's layout (AM15 via `meta.isOpen`, W4). */
const DiagnosticModuleRoutes: RouteRecordRaw[] = [...DiagnosticRoutes, ...ReportRoutes];

export { DiagnosticModuleRoutes };
