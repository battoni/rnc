import type { RouteRecordRaw } from 'vue-router';
import { ASSISTANT_PERMISSIONS, ASSISTANT_ROUTE_NAMES } from '../../constants';

/* "Perguntas para especialista" (rnc-3 R14): on `assistant.ask`; who reads it — a specialist, `assistant.manage` — is the API's (403 otherwise). */
const QuestionsRoutes: RouteRecordRaw[] = [
  {
    path: '/team/assistant/questions',
    name: `${ASSISTANT_ROUTE_NAMES.questions}-en`,
    meta: { permission: ASSISTANT_PERMISSIONS.ask },
    component: () => import('./QuestionsView.view.vue'),
  },
  {
    path: '/equipe/assistente/perguntas',
    name: `${ASSISTANT_ROUTE_NAMES.questions}-pt-BR`,
    meta: { permission: ASSISTANT_PERMISSIONS.ask },
    component: () => import('./QuestionsView.view.vue'),
  },
];

export default QuestionsRoutes;
