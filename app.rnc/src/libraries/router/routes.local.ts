import type { RouteRecordRaw } from 'vue-router';
import { FIRM_MODULE } from '@Enums';
import { AssistantModuleRoutes } from '@AssistantModule';
import { BrandsModuleRoutes } from '@BrandsModule';
import { BriefingModuleRoutes } from '@BriefingModule';
import { ChannelModuleRoutes } from '@ChannelModule';
import { ContractsModuleRoutes } from '@ContractsModule';
import { DiagnosticModuleRoutes } from '@DiagnosticModule';
import { FiscalModuleRoutes } from '@FiscalModule';
import { WorkspaceRoutes } from '@HomeModule';
import { MetricsModuleRoutes } from '@MetricsModule';
import { PartnerModuleRoutes } from '@PartnerModule';
import { PipelineModuleRoutes } from '@PipelineModule';
import { PlatformModuleRoutes } from '@PlatformModule';
import { PortalModuleRoutes } from '@PortalModule';
import { RadarModuleRoutes } from '@RadarModule';
import { RepricingModuleRoutes } from '@RepricingModule';
import { SiteRoutes } from '@SiteModule';

/**
 * Project-owned routes — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * Register your feature-module route arrays here; they are appended after the core
 * routes in routes.ts and survive every rnc update. This is the extension point
 * that keeps a sync from ever reverting your routing.
 *
 * Example:
 *   import { CatalogRoutes } from '@CatalogModule';
 *   import { ClientsRoutes } from '@ClientModule';
 *
 *   const localRoutes: RouteRecordRaw[] = [...CatalogRoutes, ...ClientsRoutes];
 */
// rnc-1: the public site (F1 landing, F2 sector pages, F3 diagnostic), the back office's three workspaces and the
// team's pipeline (F5), Reinaldo's network (F6), metrics (F7) and fee repricing (F8). rnc-2: the
// partner's portal (F1), the fiscal screens (F8) and the portfolio Radar (F10). rnc-3: the
// operator's brands console (F2), the RNC channel (F3b), the Reforma assistant (F5), the
// contract alert (F7) and Reinaldo's briefing (F9).
//
// rnc-4 F3c (R17): every screen of an opt-in module carries `meta.module` — set here, where the
// whole map is in view, not in each module's route file — and `attachAuthGuard` shows not-found
// while the firm has it off. The split is the API's `module:<key>` middleware's: the portal is the
// partner network's, repricing the portfolio's. Core (site, diagnostic, workspaces, pipeline,
// metrics) carries none. rnc-4 F4b: the platform console (R25) — core too, closed by the operator's keys.
const localRoutes: RouteRecordRaw[] = [
  ...SiteRoutes,
  ...DiagnosticModuleRoutes,
  ...WorkspaceRoutes,
  ...PipelineModuleRoutes,
  ...ofModule(FIRM_MODULE.PARTNERS, PartnerModuleRoutes),
  ...MetricsModuleRoutes,
  ...ofModule(FIRM_MODULE.PORTFOLIO, RepricingModuleRoutes),
  ...ofModule(FIRM_MODULE.PARTNERS, PortalModuleRoutes),
  ...ofModule(FIRM_MODULE.BRANDS, BrandsModuleRoutes),
  ...ofModule(FIRM_MODULE.CHANNEL, ChannelModuleRoutes),
  ...ofModule(FIRM_MODULE.ASSISTANT, AssistantModuleRoutes),
  ...ofModule(FIRM_MODULE.FISCAL, FiscalModuleRoutes),
  ...ofModule(FIRM_MODULE.RADAR, RadarModuleRoutes),
  ...ofModule(FIRM_MODULE.CONTRACTS, ContractsModuleRoutes),
  ...ofModule(FIRM_MODULE.BRIEFING, BriefingModuleRoutes),
  ...PlatformModuleRoutes,
];

function ofModule(module: FIRM_MODULE, routes: RouteRecordRaw[]): RouteRecordRaw[] {
  return routes.map((route) => ({ ...route, meta: { ...route.meta, module } }) as RouteRecordRaw);
}

export default localRoutes;
