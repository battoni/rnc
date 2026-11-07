import type { RouteRecordRaw } from 'vue-router';
import { ASSISTANT_PERMISSIONS, ASSISTANT_ROUTE_NAMES } from '../../constants';

/* "Assistente da Reforma" (rnc-3 R12, R13): the team and Reinaldo ask (`assistant.ask`). */
const AssistantRoutes: RouteRecordRaw[] = [
  {
    path: '/team/assistant',
    name: `${ASSISTANT_ROUTE_NAMES.ask}-en`,
    meta: { permission: ASSISTANT_PERMISSIONS.ask },
    component: () => import('./AssistantView.view.vue'),
  },
  {
    path: '/equipe/assistente',
    name: `${ASSISTANT_ROUTE_NAMES.ask}-pt-BR`,
    meta: { permission: ASSISTANT_PERMISSIONS.ask },
    component: () => import('./AssistantView.view.vue'),
  },
];

export default AssistantRoutes;
