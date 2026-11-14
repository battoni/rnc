import { defineComponent, h, ref } from 'vue';
import type { Ref } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { delay, http, HttpResponse } from 'msw';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { PublicFirm } from '@Interfaces';
import { server } from '@/test/msw/server';
import { brandPalette } from './brandPalette';
import { BRAND_STORAGE_KEY } from './constants';

/*
 * rnc-4 R10, A11: one app-level painter puts the host firm's palette on `<html>` for BOTH layouts —
 * mounted by `App.vue`, so the back office no longer takes its brand colours from the theme file —
 * paints the tab's last answer before the API replies, and lets a sub-brand override it only while a
 * public layout is up. The document title is the product's name wherever no public page is.
 */
const ALVORADA = {
  address: null,
  brand: null,
  brand_accent: '#f4b63f',
  brand_primary: '#0f5c4d',
  business_hours: [],
  logo_text: 'Alvorada Contabilidade',
  mailbox: null,
  name: 'Alvorada Contabilidade',
  phone: null,
  phone_href: null,
  platform: { name: 'Hub da Reforma' },
  slug: 'alvorada',
} satisfies PublicFirm;

const ALVORADA_EXPRESS = {
  ...ALVORADA,
  brand: 'express',
  brand_accent: '#ff8a3d',
  brand_primary: '#3b2a86',
} satisfies PublicFirm;

const ALVORADA_PRIMARY = brandPalette(ALVORADA.brand_primary, ALVORADA.brand_accent)['--color-primary-500'];
const EXPRESS_PRIMARY = brandPalette(ALVORADA_EXPRESS.brand_primary, ALVORADA_EXPRESS.brand_accent)[
  '--color-primary-500'
];

/** Longer than the painter's hand-back delay after the last public layout goes. */
const HAND_BACK_WAIT_MS = 1100;

/** Long after every assertion of a test, short enough to settle before the suite moves on. */
const LATE_ANSWER_MS = 300;

let isApiSilent = false;

function rootPrimary() {
  return document.documentElement.style.getPropertyValue('--color-primary-500');
}

async function freshTheme() {
  vi.resetModules();

  return import('./useBrandTheme');
}

/** `App.vue`'s call, as any layout below it sees it. */
function mountApp(useBrandTheme: () => void) {
  return mount(defineComponent({ setup: () => (useBrandTheme(), () => h('div')) }));
}

function mountPublicLayout(useSubBrandTheme: (firm: Ref<PublicFirm | null>) => void, shown: PublicFirm | null) {
  const firm = ref<PublicFirm | null>(shown);

  return mount(defineComponent({ setup: () => (useSubBrandTheme(firm), () => h('main')) }));
}

beforeEach(() => {
  isApiSilent = false;
  sessionStorage.clear();
  document.documentElement.removeAttribute('style');
  delete document.documentElement.dataset.brand;
  document.title = 'neutral';
  server.use(
    http.get('http://localhost/public/firm', async () => {
      // Late, not never: a request left hanging outlived the test and timed the suite out under load.
      if (isApiSilent) await delay(LATE_ANSWER_MS);

      return HttpResponse.json({ data: ALVORADA });
    })
  );
});

afterEach(() => {
  vi.useRealTimers();
});

describe('useBrandTheme, mounted by App.vue', () => {
  it("paints the host firm's palette on <html> for any layout and titles the page with the product", async () => {
    const { useBrandTheme } = await freshTheme();

    mountApp(useBrandTheme);
    await flushPromises();

    expect(rootPrimary()).toBe(ALVORADA_PRIMARY);
    expect(document.documentElement.dataset.brand).toBe('alvorada');
    expect(document.title).toBe('Hub da Reforma');
  });

  // rnc-4 R14: the copy's `{firmShort}` / `{firmName}` speak for the host firm, filled from the same answer.
  it('names the host firm in the translations, short name first, else the full name', async () => {
    const { useBrandTheme } = await freshTheme();
    const { default: i18n } = await import('@Libraries/i18n');

    mountApp(useBrandTheme);
    await flushPromises();

    expect(i18n.global.t('site.landing.proofLabel', {}, { locale: 'pt-BR' })).toBe('Por que a Alvorada Contabilidade');
    expect(i18n.global.t('auth.brand.tagline', {}, { locale: 'pt-BR' })).toContain('hub da Alvorada Contabilidade');
  });

  it("paints the tab's stored palette on a second mount, before the API answers", async () => {
    const first = await freshTheme();

    mountApp(first.useBrandTheme).unmount();
    await flushPromises();
    document.documentElement.removeAttribute('style');
    isApiSilent = true;

    const second = await freshTheme();

    mountApp(second.useBrandTheme);

    expect(rootPrimary()).toBe(ALVORADA_PRIMARY);
  });

  it('leaves the theme its own colours while no firm is known', async () => {
    isApiSilent = true;

    const { useBrandTheme } = await freshTheme();

    mountApp(useBrandTheme);
    await flushPromises();

    expect(rootPrimary()).toBe('');
  });
});

describe('useSubBrandTheme, called by ThePublicLayout', () => {
  it("lays a sub-brand's palette over the firm's only while a public layout is up", async () => {
    const { useBrandTheme, useSubBrandTheme } = await freshTheme();

    mountApp(useBrandTheme);
    await flushPromises();

    const publicLayout = mountPublicLayout(useSubBrandTheme, ALVORADA_EXPRESS);

    expect(rootPrimary()).toBe(EXPRESS_PRIMARY);
    expect(document.documentElement.dataset.brand).toBe('express');

    vi.useFakeTimers();
    publicLayout.unmount();
    vi.advanceTimersByTime(HAND_BACK_WAIT_MS);

    expect(rootPrimary()).toBe(ALVORADA_PRIMARY);
    expect(document.documentElement.dataset.brand).toBe('alvorada');
  });

  it("keeps the firm's palette on a public page of the main brand", async () => {
    const { useBrandTheme, useSubBrandTheme } = await freshTheme();

    mountApp(useBrandTheme);
    await flushPromises();
    mountPublicLayout(useSubBrandTheme, ALVORADA);

    expect(rootPrimary()).toBe(ALVORADA_PRIMARY);
  });

  it("paints the tab's stored sub-brand on mount, before the brand's answer", async () => {
    const first = await freshTheme();

    mountApp(first.useBrandTheme);
    await flushPromises();
    mountPublicLayout(first.useSubBrandTheme, ALVORADA_EXPRESS).unmount();
    sessionStorage.setItem(BRAND_STORAGE_KEY, 'express');

    const second = await freshTheme();

    mountApp(second.useBrandTheme);
    mountPublicLayout(second.useSubBrandTheme, null);

    expect(rootPrimary()).toBe(EXPRESS_PRIMARY);
  });

  it('does not take the title from a public page, which writes its own', async () => {
    isApiSilent = true;

    const { useBrandTheme, useSubBrandTheme } = await freshTheme();

    mountPublicLayout(useSubBrandTheme, ALVORADA);
    document.title = 'Diagnóstico · Alvorada';
    mountApp(useBrandTheme);

    expect(document.title).toBe('Diagnóstico · Alvorada');
  });
});
