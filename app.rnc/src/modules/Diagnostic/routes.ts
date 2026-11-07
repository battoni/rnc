import type { RouteRecordRaw } from 'vue-router';
import { DiagnosticRoutes } from './views';

/* The public diagnostic — open, under the site's layout (AM15 via `meta.isOpen`, W4). */
const DiagnosticModuleRoutes: RouteRecordRaw[] = [...DiagnosticRoutes];

export { DiagnosticModuleRoutes };
