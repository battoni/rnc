import type { RouteRecordRaw } from 'vue-router';
import { DiagnosticModuleRoutes } from '@DiagnosticModule';
import { WorkspaceRoutes } from '@HomeModule';
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
// rnc-1: the public site (F1 landing, F2 sector pages, F3 diagnostic) and the back office's three workspaces.
const localRoutes: RouteRecordRaw[] = [...SiteRoutes, ...DiagnosticModuleRoutes, ...WorkspaceRoutes];

export default localRoutes;
