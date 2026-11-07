import { createRouter, createWebHistory } from 'vue-router';
import { setNavigating } from '@Composables';
import attachAuthGuard from '@Helpers/attachAuthGuard.helper';
import attachLocalePathGuard from '@Helpers/i18n/attachLocalePathGuard.helper';
import routes from './routes';

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes,
});

/*
 * Locale path rewrite runs before auth so a guest hitting `/users` is remembered as `/usuarios`
 * (when the locale is pt-BR) rather than as the English bookmark they typed.
 */
attachLocalePathGuard(router);

// attachAuthGuard closes a dependency cycle back to @Libraries; this is safe only because of
// the import order documented in libraries/index.ts (@UserModule is evaluated before this runs).
attachAuthGuard(router);

/*
 * Navigation is a BOOLEAN in the app-busy signal, not a counter.
 *
 * Vue Router replaces a redirected navigation with a fresh cycle and only the final one reaches
 * `afterEach`, so a counter would leak a `begin` for every redirect the auth guard performs — and
 * this app redirects on every protected route a guest touches. A boolean absorbs that:
 * `beforeEach` may raise it as many times as it likes, and the one `afterEach` that ends the
 * story lowers it. `onError` is the third exit and must lower it too, or a failed navigation
 * leaves the app looking busy forever.
 */
router.beforeEach(() => setNavigating(true));
router.afterEach(() => setNavigating(false));
router.onError(() => setNavigating(false));

export default router;
