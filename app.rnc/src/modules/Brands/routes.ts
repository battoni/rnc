import type { RouteRecordRaw } from 'vue-router';
import { BrandsRoutes } from './views';

/* The operator's brands console (rnc-3 F2) — signed in, by permission. */
const BrandsModuleRoutes: RouteRecordRaw[] = [...BrandsRoutes];

export { BrandsModuleRoutes };
