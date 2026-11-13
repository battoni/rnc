import type { RouteRecordRaw } from 'vue-router';
import { AgendaRoutes, LeadRoutes, PipelineRoutes } from './views';

/* The team's screens (rnc-1 F5): the board, the lead detail and the agenda — signed in, by permission. */
const PipelineModuleRoutes: RouteRecordRaw[] = [...PipelineRoutes, ...LeadRoutes, ...AgendaRoutes];

export { PipelineModuleRoutes };
