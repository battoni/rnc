import type { RouteRecordRaw } from 'vue-router';
import { LandingRoutes, SectorRoutes } from './views';

/*
 * The public site's own routes — the landing and the six sector pages. The CTA's destination,
 * `/diagnostico` (`SITE_ROUTE_NAMES.diagnostic`), is the Diagnostic module's since rnc-1 F3.
 */
const SiteRoutes: RouteRecordRaw[] = [...LandingRoutes, ...SectorRoutes];

export { SiteRoutes };
