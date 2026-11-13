import type { RouteRecordRaw } from 'vue-router';
import { LedgerRoutes, PartnersRoutes, TrailRoutes } from './views';

/* Reinaldo's network screens (rnc-1 F6): the partner registry, the trail and the ledger (rnc-2 F4) — signed in, by permission. */
const PartnerModuleRoutes: RouteRecordRaw[] = [...PartnersRoutes, ...TrailRoutes, ...LedgerRoutes];

export { PartnerModuleRoutes };
