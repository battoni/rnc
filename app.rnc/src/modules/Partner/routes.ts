import type { RouteRecordRaw } from 'vue-router';
import { PartnersRoutes, TrailRoutes } from './views';

/* Reinaldo's network screens (rnc-1 F6): the partner registry and the trail — signed in, by permission. */
const PartnerModuleRoutes: RouteRecordRaw[] = [...PartnersRoutes, ...TrailRoutes];

export { PartnerModuleRoutes };
