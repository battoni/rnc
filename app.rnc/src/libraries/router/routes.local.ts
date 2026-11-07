import type { RouteRecordRaw } from 'vue-router';

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
const localRoutes: RouteRecordRaw[] = [];

export default localRoutes;
