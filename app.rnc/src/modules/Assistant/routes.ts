import type { RouteRecordRaw } from 'vue-router';
import { AssistantRoutes, KnowledgeRoutes, QuestionsRoutes } from './views';

/* The Reforma assistant's screens (rnc-3 F5) — signed in, under Equipe, by `assistant.ask`. */
const AssistantModuleRoutes: RouteRecordRaw[] = [...AssistantRoutes, ...QuestionsRoutes, ...KnowledgeRoutes];

export { AssistantModuleRoutes };
