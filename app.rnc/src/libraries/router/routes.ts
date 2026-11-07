import type { RouteRecordRaw } from 'vue-router';
import NotFoundRoutes from '@/views/NotFound/routes';
import { AuthRoutes } from '@AuthModule';
import { HomeRoutes } from '@HomeModule';
import { IdeaRoutes, IdeasRoutes, NewsRoutes } from '@IdeaModule';
import { PersonalizationRoutes } from '@PersonalizationModule';
import { BrandRoutes, ComponentsRoutes, FeaturesRoutes, ShowcaseRoutes } from '@ShowcaseModule';
import { TipRoutes } from '@TipModule';
import { UsersRoutes } from '@UserModule';
import localRoutes from './routes.local';

export default <RouteRecordRaw[]>[
  ...AuthRoutes,
  ...HomeRoutes,
  ...IdeasRoutes,
  ...IdeaRoutes,
  ...NewsRoutes,
  ...PersonalizationRoutes,
  ...BrandRoutes,
  ...ComponentsRoutes,
  ...FeaturesRoutes,
  ...ShowcaseRoutes,
  ...TipRoutes,
  ...UsersRoutes,
  ...localRoutes,
  // Last for the reader. vue-router ranks by specificity, so the catch-all loses to every real route
  // — a project's own included — wherever it is declared.
  ...NotFoundRoutes,
];
