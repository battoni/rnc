import type { RouteRecordRaw } from 'vue-router';
import { MetricsRoutes } from './views';

/* Reinaldo's metrics (rnc-1 F7): the funnel — signed in, by permission. */
const MetricsModuleRoutes: RouteRecordRaw[] = [...MetricsRoutes];

export { MetricsModuleRoutes };
