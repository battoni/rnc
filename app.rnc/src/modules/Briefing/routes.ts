import type { RouteRecordRaw } from 'vue-router';
import { BriefingRoutes } from './views';

/* "Briefing do dia" (rnc-3 F9): Reinaldo's day in one screen — signed in, by permission. */
const BriefingModuleRoutes: RouteRecordRaw[] = [...BriefingRoutes];

export { BriefingModuleRoutes };
