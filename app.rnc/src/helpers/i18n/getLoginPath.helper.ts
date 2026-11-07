import i18n from '@/libraries/i18n';

/**
 * Where a person goes when they have no session — on logout, and when the guard turns them away.
 *
 * One source for both. They used to disagree: logging out sent you to `/login` while the guard
 * sent you to `/entrar`, so the same screen answered on two URLs and which one you saw depended on
 * how you got there. Both open the same view, which is why nobody noticed and why it was worth
 * fixing rather than leaving — a URL is something people read, bookmark and paste.
 *
 * The login routes are named `auth.login` / `auth.entrar` rather than the `-pt-BR` suffix the rest
 * of the app uses, so `getI18nRouteName` cannot resolve them and this maps the locale directly.
 */
function getLoginPath(): string {
  return i18n.global.locale.value === 'pt-BR' ? '/entrar' : '/login';
}

export { getLoginPath };
