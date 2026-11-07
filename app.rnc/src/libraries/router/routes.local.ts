import type { RouteRecordRaw } from 'vue-router';
import { DiagnosticModuleRoutes } from '@DiagnosticModule';
import { WorkspaceRoutes } from '@HomeModule';
import { MetricsModuleRoutes } from '@MetricsModule';
import { PartnerModuleRoutes } from '@PartnerModule';
import { PipelineModuleRoutes } from '@PipelineModule';
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
// team's pipeline (F5), Reinaldo's network (F6), metrics (F7) and fee repricing (F8).
const localRoutes: RouteRecordRaw[] = [
  ...SiteRoutes,
  ...DiagnosticModuleRoutes,
  ...WorkspaceRoutes,
  ...PipelineModuleRoutes,
  ...PartnerModuleRoutes,
  ...MetricsModuleRoutes,
  ...RepricingModuleRoutes,
];

export default localRoutes;
