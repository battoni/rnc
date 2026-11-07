import { afterEach, describe, expect, it } from 'vitest';
import type { MessageTree } from '@/libraries/i18n/deepMergeMessages';
import deepMergeMessages from '@/libraries/i18n/deepMergeMessages';
import { mountWithPlugins } from '@/test/mount';
import i18n from '@Libraries/i18n';
import AuthFlowLayout from './AuthFlowLayout.vue';

const STUBS = { ABrandMark: true, ABrandPattern: true, ABrandSignature: true, ALogo: true, Divider: true };

const LOCALE = i18n.global.locale.value;
const CORE_MESSAGES = i18n.global.getLocaleMessage(LOCALE) as MessageTree;

function mountLayout() {
  return mountWithPlugins(AuthFlowLayout, { global: { stubs: STUBS }, props: { title: 'Sign in' } });
}

afterEach(() => i18n.global.setLocaleMessage(LOCALE, CORE_MESSAGES as never));

/*
 * Both projects that grew from this bootstrap rewrote the hero, and both had to edit a MERGE file
 * to do it — English literals in the template, conflicting on every sync. Every visible string is
 * a key now, and a project says its own line in `src/locales/local/*.json`.
 */
describe('AuthFlowLayout — the hero copy', () => {
  it('reads every visible string from auth.brand', () => {
    const wrapper = mountLayout();

    expect(wrapper.get('[data-testid="auth-brand-tag"]').text()).toBe(i18n.global.t('auth.brand.tag'));
    expect(wrapper.get('[data-testid="auth-brand-headline"]').text()).toBe(i18n.global.t('auth.brand.headline'));
    expect(wrapper.get('[data-testid="auth-brand-tagline"]').text()).toBe(i18n.global.t('auth.brand.tagline'));
    expect(wrapper.get('[data-testid="auth-brand-domain"]').text()).toBe(i18n.global.t('auth.brand.domain'));
  });

  it('says what a project put in its local locale, not the bootstrap line', () => {
    const LOCAL_OVERRIDE: MessageTree = {
      auth: {
        brand: {
          domain: 'acme.example',
          headline: 'Ours, at last.',
          tag: '>_ acme',
          tagline: 'Whatever Acme sells.',
        },
      },
    };

    i18n.global.setLocaleMessage(LOCALE, deepMergeMessages(CORE_MESSAGES, LOCAL_OVERRIDE) as never);

    const wrapper = mountLayout();

    expect(wrapper.get('[data-testid="auth-brand-tag"]').text()).toBe('>_ acme');
    expect(wrapper.get('[data-testid="auth-brand-headline"]').text()).toBe('Ours, at last.');
    expect(wrapper.get('[data-testid="auth-brand-tagline"]').text()).toBe('Whatever Acme sells.');
    expect(wrapper.get('[data-testid="auth-brand-domain"]').text()).toBe('acme.example');
    expect(wrapper.text()).not.toContain('yourbrand.dev');
  });
});

/*
 * The aside is filled with the brand. What sits on it takes `--color-primary-contrast`, which the
 * theme decides: a raw ink shade reads on a lime fill and disappears on a dark one (ltv-071).
 */
describe('AuthFlowLayout — the brand panel', () => {
  it('paints everything on the primary fill with the contrast token, never a raw ink shade', () => {
    const wrapper = mountLayout();
    const ASIDE = wrapper.get('aside');

    const PAINTED = ASIDE.findAll('*').filter((element) =>
      element
        .classes()
        .some((className) => className.startsWith('text-') && !/^text-(xs|sm|base|lg|\d?xl)$/.test(className))
    );

    expect(ASIDE.html()).not.toMatch(/text-ink-/);
    expect(PAINTED.length).toBeGreaterThanOrEqual(6);
    expect(
      PAINTED.every((element) => element.classes().some((className) => className.startsWith('text-primary-contrast')))
    ).toBe(true);
  });
});
