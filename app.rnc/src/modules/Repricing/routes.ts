import type { RouteRecordRaw } from 'vue-router';
import { RepricingLetterRoutes, RepricingRoutes } from './views';

/* Reinaldo's fee repricing (rnc-1 F8): the portfolio quoted and the printable letter — signed in, by permission. */
const RepricingModuleRoutes: RouteRecordRaw[] = [...RepricingRoutes, ...RepricingLetterRoutes];

export { RepricingModuleRoutes };
