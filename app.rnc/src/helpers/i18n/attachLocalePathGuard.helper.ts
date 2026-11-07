import type { NavigationGuardNext, RouteLocationNormalized, Router } from 'vue-router';
import i18n from '@/libraries/i18n';

/**
 * Keep the URL in the active locale.
 *
 * Both EN and PT paths stay registered so bookmarks and pasted links still resolve. Links already
 * go through `getI18nRouteName`; this guard is the other half — opening `/users` with a pt-BR
 * locale replaces into `/usuarios` (and the reverse for en), instead of leaving the address bar
 * in the wrong language.
 *
 * Auth routes are the exception to the `*-en` / `*-pt-BR` suffix and need naming in full: their
 * twins are spelled out (`auth.login` / `auth.entrar`), so no suffix rule can pair them. Every
 * pair is listed here ONCE and both directions are derived from that list — a half-entered pair
 * is how `/onboarding`, `/verify-email`, `/support`, `/forgot-password`, `/reset-password` and
 * `/terms` stayed English inside a Portuguese app while `/login` rewrote correctly.
 *
 * ADDING AN AUTH ROUTE: add its pair below, or it silently keeps whichever language was typed.
 */
const AUTH_ROUTE_PAIRS: ReadonlyArray<readonly [string, string]> = [
  ['auth.login', 'auth.entrar'],
  ['auth.loginEmail', 'auth.entrarEmail'],
  ['auth.loginCode', 'auth.entrarCodigo'],
  ['auth.onboarding', 'auth.completarCadastro'],
  ['auth.verifyEmail', 'auth.verificarEmail'],
  ['auth.support', 'auth.suporte'],
  ['auth.forgotPassword', 'auth.esqueceuSenha'],
  ['auth.resetPassword', 'auth.recuperarSenha'],
  ['auth.terms', 'auth.termos'],
];

const AUTH_TO_PT: Record<string, string> = Object.fromEntries(AUTH_ROUTE_PAIRS);

const AUTH_TO_EN: Record<string, string> = Object.fromEntries(
  AUTH_ROUTE_PAIRS.map(([englishName, portugueseName]) => [portugueseName, englishName])
);

function routeExists(router: Router, name: string): boolean {
  return router.getRoutes().some((route) => route.name === name);
}

/*
 * Every candidate is checked against the router before it is returned. `next({ name })` on a name
 * nothing registers aborts the navigation, so an unregistered twin would turn a wrong-language URL
 * into a dead one — strictly worse than the problem being fixed.
 */
function resolveLocalizedRouteName(router: Router, name: string, locale: string): string {
  const WANTS_PT = locale === 'pt-BR';

  if (WANTS_PT) {
    const MAPPED = AUTH_TO_PT[name];

    if (MAPPED && routeExists(router, MAPPED)) return MAPPED;

    if (name.endsWith('-en')) {
      const candidate = `${name.slice(0, -3)}-pt-BR`;
      if (routeExists(router, candidate)) return candidate;
    }

    if (!name.endsWith('-pt-BR') && !name.endsWith('-en')) {
      const candidate = `${name}-pt-BR`;
      if (routeExists(router, candidate)) return candidate;
    }

    return name;
  }

  const MAPPED = AUTH_TO_EN[name];

  if (MAPPED && routeExists(router, MAPPED)) return MAPPED;

  if (name.endsWith('-pt-BR')) {
    const stem = name.slice(0, -'-pt-BR'.length);
    const enName = `${stem}-en`;

    if (routeExists(router, enName)) return enName;
    if (routeExists(router, stem)) return stem;
  }

  return name;
}

function attachLocalePathGuard(router: Router): void {
  router.beforeEach((to: RouteLocationNormalized, _from, next: NavigationGuardNext) => {
    if (typeof to.name !== 'string') {
      next();
      return;
    }

    const localized = resolveLocalizedRouteName(router, to.name, i18n.global.locale.value);

    if (localized === to.name) {
      next();
      return;
    }

    next({
      name: localized,
      params: to.params,
      query: to.query,
      hash: to.hash,
      replace: true,
    });
  });
}

export { resolveLocalizedRouteName };
export default attachLocalePathGuard;
