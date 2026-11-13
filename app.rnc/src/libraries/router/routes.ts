import type { RouteRecordRaw } from 'vue-router';
import NotFoundRoutes from '@/views/NotFound/routes';
import { AuthRoutes } from '@AuthModule';
import { HomeRoutes } from '@HomeModule';
import { PersonalizationRoutes } from '@PersonalizationModule';
import { UsersRoutes } from '@UserModule';
import localRoutes from './routes.local';

export default <RouteRecordRaw[]>[
  ...AuthRoutes,
  ...HomeRoutes,
  ...PersonalizationRoutes,
  ...UsersRoutes,
  ...localRoutes,
  // Last for the reader. vue-router ranks by specificity, so the catch-all loses to every real route
  // — a project's own included — wherever it is declared.
  ...NotFoundRoutes,
];
