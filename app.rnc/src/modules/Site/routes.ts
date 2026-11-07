import type { RouteRecordRaw } from 'vue-router';
import { DiagnosticRoutes, LandingRoutes, SectorRoutes } from './views';

/* Every route of the public site — the landing, the six sector pages and the CTA's destination. */
const SiteRoutes: RouteRecordRaw[] = [...LandingRoutes, ...SectorRoutes, ...DiagnosticRoutes];

export { SiteRoutes };
