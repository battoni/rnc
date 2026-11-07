import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createMemoryHistory, createRouter } from 'vue-router';
import i18n from '@/libraries/i18n';
import attachLocalePathGuard, { resolveLocalizedRouteName } from './attachLocalePathGuard.helper';

function makeRouter() {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/users', name: 'users-en', component: { template: '<div />' } },
      { path: '/usuarios', name: 'users-pt-BR', component: { template: '<div />' } },
      { path: '/ideas', name: 'ideas-en', component: { template: '<div />' } },
      { path: '/ideias', name: 'ideas-pt-BR', component: { template: '<div />' } },
      { path: '/login', name: 'auth.login', component: { template: '<div />' } },
      { path: '/entrar', name: 'auth.entrar', component: { template: '<div />' } },
      { path: '/login-email', name: 'auth.loginEmail', component: { template: '<div />' } },
      { path: '/entrar-email', name: 'auth.entrarEmail', component: { template: '<div />' } },
      { path: '/login-confirmation', name: 'auth.loginCode', component: { template: '<div />' } },
      { path: '/confirmar-usuario', name: 'auth.entrarCodigo', component: { template: '<div />' } },
      { path: '/onboarding', name: 'auth.onboarding', component: { template: '<div />' } },
      { path: '/completar-cadastro', name: 'auth.completarCadastro', component: { template: '<div />' } },
      { path: '/verify-email', name: 'auth.verifyEmail', component: { template: '<div />' } },
      { path: '/verificar-email', name: 'auth.verificarEmail', component: { template: '<div />' } },
      { path: '/support', name: 'auth.support', component: { template: '<div />' } },
      { path: '/suporte', name: 'auth.suporte', component: { template: '<div />' } },
      { path: '/forgot-password', name: 'auth.forgotPassword', component: { template: '<div />' } },
      { path: '/esqueci-minha-senha', name: 'auth.esqueceuSenha', component: { template: '<div />' } },
      { path: '/reset-password', name: 'auth.resetPassword', component: { template: '<div />' } },
      { path: '/recuperar-senha', name: 'auth.recuperarSenha', component: { template: '<div />' } },
      { path: '/terms', name: 'auth.terms', component: { template: '<div />' } },
      { path: '/termos', name: 'auth.termos', component: { template: '<div />' } },
      { path: '/', name: 'home', component: { template: '<div />' } },
    ],
  });
}

/*
 * The auth pairs, listed here independently of the helper's own table. The first version of this
 * guard covered login, login-email and login-confirmation and quietly left the other six English
 * inside a Portuguese app — a hole no suffix rule catches, because these names are spelled out
 * rather than suffixed. Asserting the pairs from the outside is what makes a half-added route
 * fail here instead of in someone's address bar.
 */
const AUTH_PAIRS: ReadonlyArray<readonly [string, string]> = [
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

describe('resolveLocalizedRouteName', () => {
  it('maps EN stems to pt-BR when the locale wants Portuguese', () => {
    const router = makeRouter();

    expect(resolveLocalizedRouteName(router, 'users-en', 'pt-BR')).toBe('users-pt-BR');
    expect(resolveLocalizedRouteName(router, 'ideas-en', 'pt-BR')).toBe('ideas-pt-BR');
  });

  it('maps pt-BR stems to EN when the locale wants English', () => {
    const router = makeRouter();

    expect(resolveLocalizedRouteName(router, 'users-pt-BR', 'en')).toBe('users-en');
    expect(resolveLocalizedRouteName(router, 'ideas-pt-BR', 'en')).toBe('ideas-en');
  });

  it.each(AUTH_PAIRS)('maps %s ↔ %s by locale', (englishName, portugueseName) => {
    const router = makeRouter();

    expect(resolveLocalizedRouteName(router, englishName, 'pt-BR')).toBe(portugueseName);
    expect(resolveLocalizedRouteName(router, portugueseName, 'en')).toBe(englishName);
  });

  it('leaves an auth name alone when its twin is not registered', () => {
    // `next({ name })` on an unregistered name aborts the navigation, which would turn a
    // wrong-language URL into a dead one — strictly worse than the problem being solved.
    const router = createRouter({
      history: createMemoryHistory(),
      routes: [{ path: '/terms', name: 'auth.terms', component: { template: '<div />' } }],
    });

    expect(resolveLocalizedRouteName(router, 'auth.terms', 'pt-BR')).toBe('auth.terms');
  });

  it('leaves a matching locale alone', () => {
    const router = makeRouter();

    expect(resolveLocalizedRouteName(router, 'users-pt-BR', 'pt-BR')).toBe('users-pt-BR');
    expect(resolveLocalizedRouteName(router, 'users-en', 'en')).toBe('users-en');
    expect(resolveLocalizedRouteName(router, 'home', 'pt-BR')).toBe('home');
  });
});

describe('attachLocalePathGuard', () => {
  const ORIGINAL_LOCALE = i18n.global.locale.value;

  beforeEach(() => {
    i18n.global.locale.value = 'en';
  });

  afterEach(() => {
    i18n.global.locale.value = ORIGINAL_LOCALE;
  });

  it('replace-redirects an EN path to the PT twin when the locale is pt-BR', async () => {
    i18n.global.locale.value = 'pt-BR';
    const router = makeRouter();
    attachLocalePathGuard(router);

    await router.push('/users');

    expect(router.currentRoute.value.name).toBe('users-pt-BR');
    expect(router.currentRoute.value.path).toBe('/usuarios');
  });

  it('replace-redirects auth.login to auth.entrar when the locale is pt-BR', async () => {
    i18n.global.locale.value = 'pt-BR';
    const router = makeRouter();
    attachLocalePathGuard(router);

    await router.push('/login');

    expect(router.currentRoute.value.name).toBe('auth.entrar');
    expect(router.currentRoute.value.path).toBe('/entrar');
  });

  /*
   * The whole sign-up journey, which is what the first version of the guard missed: every one of
   * these landed on its English path inside a Portuguese app while `/login` rewrote correctly.
   */
  it.each([
    ['/onboarding', '/completar-cadastro'],
    ['/verify-email', '/verificar-email'],
    ['/support', '/suporte'],
    ['/forgot-password', '/esqueci-minha-senha'],
    ['/reset-password', '/recuperar-senha'],
    ['/terms', '/termos'],
  ])('replace-redirects %s to %s when the locale is pt-BR', async (englishPath, portuguesePath) => {
    i18n.global.locale.value = 'pt-BR';
    const router = makeRouter();
    attachLocalePathGuard(router);

    await router.push(englishPath);

    expect(router.currentRoute.value.path).toBe(portuguesePath);
  });

  it('replace-redirects a PT path to the EN twin when the locale is en', async () => {
    i18n.global.locale.value = 'en';
    const router = makeRouter();
    attachLocalePathGuard(router);

    await router.push('/ideias');

    expect(router.currentRoute.value.name).toBe('ideas-en');
    expect(router.currentRoute.value.path).toBe('/ideas');
  });

  it('does not redirect when the path already matches the locale', async () => {
    i18n.global.locale.value = 'en';
    const router = makeRouter();
    attachLocalePathGuard(router);

    await router.push('/users');

    expect(router.currentRoute.value.name).toBe('users-en');
    expect(router.currentRoute.value.path).toBe('/users');
  });
});
