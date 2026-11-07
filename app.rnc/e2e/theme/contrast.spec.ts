import type { Page } from '@playwright/test';
import { test, expect, mockUser, seedAuthSession } from '../fixtures/auth';

/*
 * What sits ON a filled brand surface has a token, and the token resolves.
 *
 * `text-primary-contrast` was being written by components before any theme defined it — the same
 * silent shape as the relief utilities: an undefined `var()` resolves to nothing, so the label
 * simply inherited whatever colour it would have had. On this bootstrap's lime that inherited
 * colour happened to be readable; on a brand with a dark accent it is a dark label on a dark
 * fill, and nothing anywhere reports it.
 *
 * A unit test cannot ask this — jsdom has no cascade. It also cannot compute a contrast ratio
 * against a colour it never resolved.
 */

test.use({ viewport: { width: 1280, height: 800 } });

function luminance([r, g, b]: number[]) {
  const channels = [r, g, b].map((value) => {
    const srgb = value / 255;

    return srgb <= 0.03928 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
  });

  return 0.2126 * channels[0]! + 0.7152 * channels[1]! + 0.0722 * channels[2]!;
}

function contrastRatio(first: number[], second: number[]) {
  const [lighter, darker] = [luminance(first), luminance(second)].sort((a, b) => b - a);

  return (lighter! + 0.05) / (darker! + 0.05);
}

function parseRgb(colour: string) {
  return (colour.match(/\d+(\.\d+)?/g) ?? []).slice(0, 3).map(Number);
}

test.describe('primary contrast', () => {
  test('the active theme defines what reads on a filled brand surface', async ({ page }) => {
    await seedAuthSession(page);
    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
    );
    await page.goto('/painel');

    const declarations = await page.evaluate(() => {
      const found: string[] = [];

      function walk(rules: CSSRuleList) {
        for (const rule of Array.from(rules)) {
          if ('cssRules' in rule) walk((rule as CSSGroupingRule).cssRules);

          if (!(rule instanceof CSSStyleRule)) continue;

          const value = rule.style.getPropertyValue('--color-primary-contrast').trim();

          if (value) found.push(value);
        }
      }

      for (const sheet of Array.from(document.styleSheets)) walk(sheet.cssRules);

      return found;
    });

    /*
     * The THEME has to be what answers, not the PrimeVue preset.
     *
     * `tailwindcss-primeui` maps this same name to `--p-primary-contrast-color`, so asserting
     * only that the token is non-empty passes with the theme's declaration deleted — the preset
     * quietly answers instead, in a colour the brand never chose. So the question goes to the
     * stylesheets: some rule other than the plugin's must declare the token.
     *
     * Retired: comparing the resolved token with the theme's `--color-ink-900`. That was
     * battoni-dev's ANSWER (dark ink on lime), not the rule, and it failed every brand whose
     * fill takes white — Terra (chapaja) and lifeshifter's terracotta — while their themes
     * declared the token exactly as this test exists to demand.
     */
    const fromTheme = declarations.filter((value) => !value.includes('--p-'));

    expect(fromTheme, '--color-primary-contrast is answered by PrimeVue, not by the theme').not.toHaveLength(0);
  });

  test('a label on the brand fill clears 4.5:1, the floor for normal text', async ({ page }) => {
    await seedAuthSession(page);
    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
    );
    await page.goto('/painel');

    const measured = await page.evaluate(() => {
      const probe = document.createElement('span');
      probe.className = 'bg-primary-500 text-primary-contrast';
      probe.textContent = 'probe';
      document.body.appendChild(probe);

      const style = window.getComputedStyle(probe);
      const out = { colour: style.color, background: style.backgroundColor };

      probe.remove();

      return out;
    });

    // 4.5:1 because a button label is normal text. WCAG's 3:1 is for large text only, and the
    // labels this token exists for are neither large nor bold.
    expect(contrastRatio(parseRgb(measured.colour), parseRgb(measured.background))).toBeGreaterThanOrEqual(4.5);
  });
});

/*
 * The two faint text tiers, on every ground they are written on, in both schemes.
 *
 * `muted` carries hints, timestamps, subtitles and labels, so it is normal text: 4.5:1. `subtle`
 * carries icons and marks and never a sentence, so its floor is the non-text one: 3:1. In
 * `battoni-dev` light, `muted` was 3.71:1 on the panel and `subtle` 1.72:1 — and 57 sentences were
 * written in `subtle`. A token that fails here fails on every screen at once.
 */
const TEXT_TIERS = [
  { token: '--color-muted', floor: 4.5 },
  { token: '--color-subtle', floor: 3 },
  // PrimeVue paints its own secondary text, placeholders, float labels and field icons from these,
  // and Aura's light defaults were 3.43:1 (placeholder) and 1.90:1 (icon) on white.
  { token: '--p-text-muted-color', floor: 4.5 },
  { token: '--p-form-field-placeholder-color', floor: 4.5 },
  { token: '--p-form-field-float-label-color', floor: 4.5 },
  // A float label that holds a value (`active`) or sits in a focused field is still the same label.
  // Aura painted them `surface.500` (3.11:1 on the panel) and `primary.600` (1.69:1 on white).
  { token: '--p-form-field-float-label-active-color', floor: 4.5 },
  { token: '--p-form-field-float-label-focus-color', floor: 4.5 },
  { token: '--p-form-field-icon-color', floor: 3 },
] as const;

const GROUNDS = ['--color-canvas', '--color-panel', '--color-panel-muted'] as const;

for (const scheme of ['light', 'dark'] as const) {
  test(`muted clears 4.5:1 and subtle clears 3:1 on every ground, ${scheme}`, async ({ page }) => {
    await seedAuthSession(page);
    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: mockUser }) })
    );
    await page.goto('/painel');

    const pairs = await page.evaluate(
      ({ grounds, schemeName, tiers }) => {
        document.documentElement.setAttribute('data-theme', schemeName);

        const probe = document.createElement('span');
        document.body.appendChild(probe);

        const resolved = tiers.flatMap(({ token }) =>
          grounds.map((ground) => {
            probe.style.color = `var(${token})`;
            probe.style.backgroundColor = `var(${ground})`;

            const style = window.getComputedStyle(probe);

            return { token, ground, colour: style.color, background: style.backgroundColor };
          })
        );

        probe.remove();

        return resolved;
      },
      { grounds: [...GROUNDS], schemeName: scheme, tiers: TEXT_TIERS.map(({ token }) => ({ token })) }
    );

    expect(pairs).toHaveLength(TEXT_TIERS.length * GROUNDS.length);

    for (const pair of pairs) {
      const floor = TEXT_TIERS.find((tier) => tier.token === pair.token)!.floor;
      const ratio = contrastRatio(parseRgb(pair.colour), parseRgb(pair.background));

      expect(ratio, `${pair.token} on ${pair.ground} (${scheme}): ${ratio.toFixed(2)}:1`).toBeGreaterThanOrEqual(floor);
    }
  });
}

/*
 * Every filled button's label, on its own fill, at rest, hovered and pressed, in both schemes.
 *
 * The preset decides these, and it decided them by palette name rather than by measurement: white
 * on `success.500` was 2.28:1, on `warn.500` 2.15:1, on `info.500` 3.68:1 and on `danger.500`
 * 3.76:1 — and in dark the state ramps LIGHTEN under the hand (500 → 400 → 300), so a white label
 * got fainter the harder it was pressed. Only `secondary` was measured here, so none of it
 * reported. The table in `styles/theme/RULES.md` ("What sits ON a fill") is this list's answer.
 */
const FILLED_SEVERITIES = ['primary', 'secondary', 'success', 'info', 'warn', 'danger', 'help', 'contrast'] as const;
const BUTTON_STATES = ['', 'hover-', 'active-'] as const;

/** Resolves `colour` over `background` on a probe, so the cascade — scheme included — answers. */
async function resolvePairs(page: Page, schemeName: string, pairs: [string, string][]) {
  return page.evaluate(
    ({ scheme, tokenPairs }) => {
      document.documentElement.setAttribute('data-theme', scheme);

      const probe = document.createElement('span');
      document.body.appendChild(probe);

      const resolved = tokenPairs.map(([colour, background]) => {
        probe.style.color = `var(${colour})`;
        probe.style.backgroundColor = `var(${background})`;

        const style = window.getComputedStyle(probe);

        return { label: `${colour} on ${background}`, colour: style.color, background: style.backgroundColor };
      });

      probe.remove();

      return resolved;
    },
    { scheme: schemeName, tokenPairs: pairs }
  );
}

function expectReadable(pairs: { label: string; colour: string; background: string }[], scheme: string) {
  for (const pair of pairs) {
    const ratio = contrastRatio(parseRgb(pair.colour), parseRgb(pair.background));

    expect(pair.colour, `${pair.label} did not resolve`).not.toBe('');
    expect(pair.background, `${pair.label} has no fill to measure against`).not.toBe('rgba(0, 0, 0, 0)');
    expect(ratio, `${pair.label} (${scheme}): ${ratio.toFixed(2)}:1`).toBeGreaterThanOrEqual(4.5);
  }
}

for (const scheme of ['light', 'dark'] as const) {
  test(`every filled button label clears 4.5:1 in every state, ${scheme}`, async ({ page }) => {
    // PrimeVue injects a component's variables only once it renders one; the login screen has buttons.
    await page.goto('/login');
    await page.getByRole('button').first().waitFor();

    const pairs = FILLED_SEVERITIES.flatMap((severity) =>
      BUTTON_STATES.map(
        (state) =>
          [`--p-button-${severity}-${state}color`, `--p-button-${severity}-${state}background`] as [string, string]
      )
    );

    const resolved = await resolvePairs(page, scheme, pairs);

    expect(resolved).toHaveLength(FILLED_SEVERITIES.length * BUTTON_STATES.length);
    expectReadable(resolved, scheme);
  });
}

/*
 * The brand ramp as TEXT, where the preset uses it that way.
 *
 * In `battoni-dev` the ramp is a lime: `primary.500` is 1.51:1 on white and `primary.700` 1.93:1.
 * A fill, never a word. The outlined, text and link buttons, the selected option of a list and the
 * active tab all wrote a word with it. The step used now is the darkest one the ramp has.
 */
const BRAND_TEXT_PAIRS: [string, string][] = [
  ['--p-button-outlined-primary-color', '--color-canvas'],
  ['--p-button-outlined-primary-color', '--p-button-outlined-primary-hover-background'],
  ['--p-button-outlined-primary-color', '--p-button-outlined-primary-active-background'],
  ['--p-button-text-primary-color', '--color-canvas'],
  ['--p-button-text-primary-color', '--p-button-text-primary-hover-background'],
  ['--p-button-text-primary-color', '--p-button-text-primary-active-background'],
  ['--p-button-link-color', '--color-canvas'],
  ['--p-button-link-hover-color', '--color-canvas'],
  ['--p-button-link-active-color', '--color-canvas'],
  ['--p-highlight-color', '--p-highlight-background'],
  ['--p-highlight-focus-color', '--p-highlight-focus-background'],
];

// The preset's variables are global: any signed-in screen resolves them (the gallery that used to
// host this left with the Showcase module, rnc-1 F1).
const DESIGN_USER = mockUser;

async function openComponents(page: Page) {
  await seedAuthSession(page);
  await page.route('**/auth/me**', (route) =>
    route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: DESIGN_USER }) })
  );
  await page.goto('/painel');
  await page.locator('main').first().waitFor();
}

for (const scheme of ['light', 'dark'] as const) {
  test(`brand text in the preset clears 4.5:1, ${scheme}`, async ({ page }) => {
    await openComponents(page);

    const resolved = await resolvePairs(page, scheme, BRAND_TEXT_PAIRS);

    for (const pair of resolved) {
      // A hover background of `transparent` or a 4% tint is measured on the canvas it sits on.
      pair.background = pair.background === 'rgba(0, 0, 0, 0)' ? 'canvas' : pair.background;
    }

    const canvas = await page.evaluate(() => window.getComputedStyle(document.body).backgroundColor);

    expectReadable(
      resolved.map((pair) => ({ ...pair, background: pair.background === 'canvas' ? canvas : pair.background })),
      scheme
    );
  });
}

/*
 * What actually renders: the colour an element is painted in, against the first opaque ground
 * behind it. A token can be right and a stylesheet can still outvote it — the global `.p-badge`
 * rule painted every badge lime with white text (1.51:1) whatever its severity said.
 */
/**
 * A colour mid-transition is neither the old one nor the new one, and the scheme switch and a hover
 * both start transitions. Measured with them off, the value is the one the rule declares.
 */
async function stillTransitions(page: Page) {
  await page.addStyleTag({ content: '*, *::before, *::after { transition: none !important; }' });
}

async function renderedPairs(page: Page, selector: string) {
  return page.evaluate((query) => {
    function ground(element: Element | null): string {
      for (let node = element; node; node = node.parentElement) {
        const background = window.getComputedStyle(node).backgroundColor;

        if (background !== 'rgba(0, 0, 0, 0)' && !background.endsWith(', 0)')) return background;
      }

      return window.getComputedStyle(document.body).backgroundColor;
    }

    return [...document.querySelectorAll(query)].map((element) => ({
      label: `${query} "${element.textContent?.trim().slice(0, 24)}" (${element.className.toString().slice(0, 60)})`,
      colour: window.getComputedStyle(element).color,
      background: ground(element),
    }));
  }, selector);
}

/*
 * The `celer-button-*` helpers, which a ConfirmPopup takes as a class because it cannot take a
 * severity. They carried their own label colours with `!important` — white on `warn.500` (2.15:1),
 * on `danger.500` (3.76:1), on `surface.500` (3.43:1) — and `surface-600` for the secondary text and
 * link buttons (4.09:1 on white). Measured on a real button, at rest, hovered and pressed.
 */
// Each helper with the PrimeVue variant it is used with, so the probe renders the button a screen does.
const HELPER_CLASSES = [
  'celer-button-primary',
  'celer-button-soft-primary',
  'celer-button-secondary',
  'celer-button-warning',
  'celer-button-danger',
  'celer-button-outlined-secondary p-button-outlined',
  'celer-button-text-secondary p-button-text',
  'celer-button-link-secondary p-button-link',
  'celer-button-link-primary p-button-link',
] as const;

for (const scheme of ['light', 'dark'] as const) {
  test(`the celer-button helpers clear 4.5:1 in every state, ${scheme}`, async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('button').first().waitFor();
    await stillTransitions(page);
    await page.evaluate(
      ({ name, classes }) => {
        document.documentElement.setAttribute('data-theme', name);

        const shelf = document.createElement('div');
        shelf.style.cssText =
          'position:fixed;inset:0;z-index:99999;display:flex;flex-wrap:wrap;gap:24px;padding:24px;background:var(--color-canvas)';
        shelf.id = 'helper-shelf';

        for (const helper of classes) {
          const button = document.createElement('button');
          button.className = `p-button p-component ${helper}`;
          button.dataset.helper = helper.split(' ')[0];
          button.innerHTML = '<span class="p-button-label">Label</span>';
          shelf.appendChild(button);
        }

        document.body.appendChild(shelf);
      },
      { name: scheme, classes: [...HELPER_CLASSES] }
    );

    const measured: { label: string; colour: string; background: string }[] = [];

    for (const helper of HELPER_CLASSES.map((classes) => classes.split(' ')[0]!)) {
      const button = page.locator(`[data-helper="${helper}"]`);

      for (const state of ['rest', 'hover', 'active'] as const) {
        await page.mouse.move(0, 0);

        if (state !== 'rest') await button.hover();
        if (state === 'active') await page.mouse.down();

        const [pair] = await renderedPairs(page, `[data-helper="${helper}"] .p-button-label`);

        if (state === 'active') await page.mouse.up();

        measured.push({ ...pair!, label: `${helper} ${state}` });
      }
    }

    expectReadable(measured, scheme);
  });
}

/*
 * The mobile drawer is a ground of its own. The faint tiers were measured on canvas, panel and
 * panel-muted, and the drawer painted `--p-drawer-background` — `#252626` in dark, where `muted`
 * measured 4.43:1 and the group chevron (`subtle`) 2.99:1. It is measured as it renders, so
 * whichever layer paints the drawer — the preset or the component — is the one asked.
 */
for (const scheme of ['light', 'dark'] as const) {
  test(`muted and subtle clear their floors on the mobile drawer, ${scheme}`, async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    await seedAuthSession(page);
    await page.route('**/auth/me**', (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: DESIGN_USER }) })
    );
    await page.goto('/painel');
    await page.locator('.mobile-navbar-root button').first().click();

    const drawer = page.locator('[data-pc-name="drawer"]');
    await drawer.waitFor();

    const measured = await drawer.evaluate((element, name) => {
      document.documentElement.setAttribute('data-theme', name);

      // The ground a child link sits on: the first opaque background above the scroll area.
      const scroller = element.querySelector('.overflow-y-auto') ?? element;
      let ground = 'rgba(0, 0, 0, 0)';

      for (let node: Element | null = scroller; node; node = node.parentElement) {
        const background = window.getComputedStyle(node).backgroundColor;

        if (background !== 'rgba(0, 0, 0, 0)') {
          ground = background;
          break;
        }
      }

      const probe = document.createElement('span');
      scroller.appendChild(probe);

      const tiers = ['--color-muted', '--color-subtle'].map((token) => {
        probe.style.color = `var(${token})`;

        return { token, colour: window.getComputedStyle(probe).color };
      });

      probe.remove();

      return { ground, tiers };
    }, scheme);

    for (const tier of measured.tiers) {
      const floor = tier.token === '--color-muted' ? 4.5 : 3;
      const ratio = contrastRatio(parseRgb(tier.colour), parseRgb(measured.ground));

      expect(
        ratio,
        `${tier.token} on the drawer ${measured.ground} (${scheme}): ${ratio.toFixed(2)}:1`
      ).toBeGreaterThanOrEqual(floor);
    }
  });
}

/*
 * The Tag helpers in `styles/components/tag.css`, which every list organism maps a tone to
 * (`tagClassByTone`). They paint with `!important` and do not flip with the scheme, so each pair is
 * measured on its own fill — and in dark as well, where the chip is still the pale tint.
 * `celer-tag-soft-primary` wrote `primary-700` on `primary-100`: 1.83:1 in `battoni-dev`.
 */
const TAG_HELPERS = [
  'celer-tag-soft-primary',
  'celer-tag-soft-success',
  'celer-tag-soft-info',
  'celer-tag-soft-warn',
  'celer-tag-soft-danger',
  'celer-tag-soft-contrast',
  'celer-tag-bold-primary',
] as const;

for (const scheme of ['light', 'dark'] as const) {
  test(`the celer-tag helpers and the default Tag clear 4.5:1, ${scheme}`, async ({ page }) => {
    await page.goto('/login');
    await page.getByRole('button').first().waitFor();
    await stillTransitions(page);
    await page.evaluate(
      ({ name, classes }) => {
        document.documentElement.setAttribute('data-theme', name);

        const shelf = document.createElement('div');
        shelf.style.cssText =
          'position:fixed;inset:0;z-index:99999;display:flex;flex-wrap:wrap;gap:24px;padding:24px;background:var(--color-canvas)';

        for (const helper of ['', ...classes]) {
          const tag = document.createElement('span');
          tag.className = `p-tag p-component ${helper}`.trim();
          tag.dataset.tagProbe = helper || 'default';

          // An empty `data-p` is what a default Tag (no severity, not rounded) renders with.
          if (!helper) tag.setAttribute('data-p', '');

          tag.innerHTML = '<span class="p-tag-label">Label</span>';
          shelf.appendChild(tag);
        }

        document.body.appendChild(shelf);
      },
      { name: scheme, classes: [...TAG_HELPERS] }
    );

    const measured = await renderedPairs(page, '[data-tag-probe]');

    expect(measured).toHaveLength(TAG_HELPERS.length + 1);
    expectReadable(measured, scheme);
  });
}
