import type { RouteRecordRaw } from 'vue-router';
import { NfeImportRoutes, ReadinessRoutes, SupplierCreditRoutes } from './views';

/* The fiscal screens (rnc-2 F8): the team's readiness and supplier credit, Reinaldo's XML import — signed in, by permission. */
const FiscalModuleRoutes: RouteRecordRaw[] = [...ReadinessRoutes, ...SupplierCreditRoutes, ...NfeImportRoutes];

export { FiscalModuleRoutes };
