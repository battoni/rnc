import type { RouteRecordRaw } from 'vue-router';
import { ContractsRoutes } from './views';

/* "Alerta de contratos" (rnc-3 F7): Reinaldo's contract alert, its import and its legal referral — signed in, by permission. */
const ContractsModuleRoutes: RouteRecordRaw[] = [...ContractsRoutes];

export { ContractsModuleRoutes };
