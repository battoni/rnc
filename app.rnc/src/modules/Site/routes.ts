import type { RouteRecordRaw } from 'vue-router';
import { LandingRoutes, PrivacyRoutes, SectorRoutes } from './views';

/*
 * The public site's own routes — the landing and the six sector pages. The CTA's destination,
 * `/diagnostico` (`SITE_ROUTE_NAMES.diagnostic`), is the Diagnostic module's since rnc-1 F3. The
 * privacy notice `/privacidade` is rnc-4 F9b's (R38).
 */
const SiteRoutes: RouteRecordRaw[] = [...LandingRoutes, ...SectorRoutes, ...PrivacyRoutes];

export { SiteRoutes };
