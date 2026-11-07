import type { RouteRecordRaw } from 'vue-router';
import { ASSISTANT_PERMISSIONS, ASSISTANT_ROUTE_NAMES } from '../../constants';

/* "Base da Reforma" (rnc-3 R10, R11, AM4): on `assistant.ask`; the API holds it to `assistant.manage` or a specialist (403 otherwise), and the rail shows it only to them. */
const KnowledgeRoutes: RouteRecordRaw[] = [
  {
    path: '/team/knowledge',
    name: `${ASSISTANT_ROUTE_NAMES.knowledge}-en`,
    meta: { permission: ASSISTANT_PERMISSIONS.ask },
    component: () => import('./KnowledgeView.view.vue'),
  },
  {
    path: '/equipe/base-da-reforma',
    name: `${ASSISTANT_ROUTE_NAMES.knowledge}-pt-BR`,
    meta: { permission: ASSISTANT_PERMISSIONS.ask },
    component: () => import('./KnowledgeView.view.vue'),
  },
];

export default KnowledgeRoutes;
