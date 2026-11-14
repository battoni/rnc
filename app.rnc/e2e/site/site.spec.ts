import type { Page } from '@playwright/test';
import { expect, seedLanguage, test } from '../fixtures/auth';

/*
 * A3 (rnc-1 F2, R5, R6, R8): the landing and the six sector pages render with Franco's brand, the
 * countdown, at least three dated facts, the CTA and their own title — with the API mocked. Measured
 * in Portuguese (e2e/RULES.md), and at 390 px and 1440 px for horizontal scroll (A15).
 */

const FIRM = {
  name: 'Franco Contabilidade',
  short_name: 'Franco',
  // rnc-4 F2c2 (C10): the proof strip is the firm's own row, as FirmSeeder writes it.
  proof: [
    { value: '35 anos', label: 'em São Paulo' },
    { value: '500+', label: 'clientes atendidos' },
    { value: 'ISO 9001', label: 'qualidade certificada' },
    { value: 'RNC', label: 'Rede Nacional de Contabilidade' },
  ],
  slug: 'franco',
  logo_text: 'Franco Contabilidade',
  brand_primary: '#00375a',
  brand_accent: '#14c3ff',
  address: 'Av. Corifeu de Azevedo Marques, 2333, 1º e 2º andares, Butantã, São Paulo-SP',
  phone: '11 3732-5500',
  phone_href: 'tel:+551137325500',
  mailbox: 'saf@franco-rnc.com.br',
  business_hours: [
    { weekdays: [1, 2, 3, 4], from: '08:00', to: '18:00' },
    { weekdays: [5], from: '08:00', to: '17:00' },
  ],
};

const SECTORS = [
  { slug: 'comercio', label: 'Comércio', article: 'o comércio' },
  { slug: 'industria', label: 'Indústria', article: 'a indústria' },
  { slug: 'servicos', label: 'Serviços', article: 'serviços' },
  { slug: 'saude', label: 'Saúde', article: 'a saúde' },
  { slug: 'educacao', label: 'Educação', article: 'a educação' },
  { slug: 'agro', label: 'Agro', article: 'o agro' },
];

function sectorPage(slug: string) {
  const sector = SECTORS.find((entry) => entry.slug === slug)!;
  const source = { label: 'LC 214/2025', url: 'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm' };

  return {
    slug,
    label: sector.label,
    title: `Reforma Tributária para ${sector.article}`,
    lede: `O que muda para ${sector.article}, com as datas da lei.`,
    facts: [
      { date: '2026', text: 'Ano-teste com CBS 0,9% e IBS 0,1% destacados na nota fiscal.', source },
      { date: '01/01/2027', text: 'A CBS passa a ser cobrada integralmente e PIS/Cofins deixam de existir.', source },
      { date: '2033', text: 'ICMS e ISS extintos, IBS e CBS integrais.', source: { label: 'LC 214/2025', url: null } },
    ],
    seo: {
      title: `Reforma Tributária em ${sector.label}: o que muda`,
      description: `As datas da Reforma para ${sector.article}.`,
    },
  };
}

function sectorSummary(slug: string) {
  const { label, lede, title } = sectorPage(slug);

  return { slug, label, title, lede };
}

async function mockSiteApi(page: Page) {
  const json = (data: unknown, status = 200) => ({
    status,
    contentType: 'application/json',
    body: JSON.stringify(data),
  });

  await page.route('**/public/firm', (route) => route.fulfill(json({ data: FIRM })));
  await page.route('**/public/sectors', (route) =>
    route.fulfill(json({ data: SECTORS.map(({ slug }) => sectorSummary(slug)) }))
  );
  await page.route(/\/public\/sectors\/[a-z-]+$/, (route) => {
    const slug = new URL(route.request().url()).pathname.split('/').pop()!;
    const isKnown = SECTORS.some((sector) => sector.slug === slug);

    return route.fulfill(
      isKnown ? json({ data: sectorPage(slug) }) : json({ error: 'content.sectorPage.notFound' }, 404)
    );
  });
  await page.route('**/public/events', (route) =>
    route.fulfill({ status: 201, contentType: 'application/json', body: '[]' })
  );
}

async function horizontalOverflow(page: Page) {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

test.beforeEach(async ({ page }) => {
  await seedLanguage(page, 'pt-BR');
  await mockSiteApi(page);
});

test.describe('the landing', () => {
  test('opens with the brand, the CBS date, the countdown, the proof points and the CTA', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByRole('banner')).toContainText('Franco');
    await expect(page.getByRole('heading', { level: 1 })).toContainText('A CBS começa a ser cobrada em 01/01/2027.');
    await expect(page.getByTestId('countdown').locator('[data-part]')).toHaveCount(4);
    await expect(page.getByTestId('proof')).toContainText('500+');
    await expect(page.getByTestId('hero-cta')).toBeVisible();
    await expect(page.getByTestId('reforma-timeline').locator('li')).toHaveCount(8);
    await expect(page.getByTestId('sector-link')).toHaveCount(6);
    await expect(page).toHaveTitle('Reforma Tributária 2026–2033: o que muda na sua empresa · Franco Contabilidade');
  });

  test("carries Franco's public contact and hours in the footer", async ({ page }) => {
    await page.goto('/');

    const footer = page.getByTestId('site-footer');

    await expect(footer).toContainText('Av. Corifeu de Azevedo Marques, 2333');
    await expect(footer.locator('a[href="tel:+551137325500"]')).toBeVisible();
    await expect(footer).toContainText('Segunda a quinta, 8h às 18h');
  });

  test('takes the visitor from the hero CTA to the diagnostic', async ({ page }) => {
    await page.goto('/');

    await page.getByTestId('hero-cta').click();

    await expect(page).toHaveURL('/diagnostico');
    await expect(page.getByTestId('diagnostic')).toBeVisible();
  });
});

/** A3 asks for ≥ 3 dated facts per sector page: the seeder may grow, the floor may not drop. */
async function expectAtLeastThreeFacts(page: Page) {
  const facts = page.getByTestId('sector-fact');

  await expect(facts.nth(2)).toBeVisible();
  expect(await facts.count()).toBeGreaterThanOrEqual(3);
}

test.describe('the sector pages', () => {
  for (const sector of SECTORS) {
    test(`/reforma/${sector.slug} renders its dated facts, the CTA and its own title`, async ({ page }) => {
      await page.goto(`/reforma/${sector.slug}`);

      await expect(page.getByRole('heading', { level: 1 })).toHaveText(`Reforma Tributária para ${sector.article}`);
      await expectAtLeastThreeFacts(page);
      await expect(page.getByTestId('sector-fact').first()).toContainText('2026');
      await expect(page.getByTestId('sector-cta')).toBeVisible();
      await expect(page).toHaveTitle(`Reforma Tributária em ${sector.label}: o que muda · Franco Contabilidade`);
      await expect(page.locator('meta[property="og:title"]')).toHaveAttribute(
        'content',
        `Reforma Tributária em ${sector.label}: o que muda`
      );
    });
  }

  test('reaches a sector from the landing grid', async ({ page }) => {
    await page.goto('/');

    await page.locator('[data-testid="sector-link"][data-sector="saude"]').click();

    await expect(page).toHaveURL('/reforma/saude');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Reforma Tributária para a saúde');
  });

  test('says a sector that does not exist does not exist', async ({ page }) => {
    await page.goto('/reforma/mineracao');

    await expect(page.getByTestId('sector-missing')).toBeVisible();
  });
});

test.describe('on a phone and on a desktop', () => {
  for (const width of [390, 1440]) {
    test(`nothing scrolls sideways at ${width} px`, async ({ page }) => {
      await page.setViewportSize({ width, height: 900 });

      await page.goto('/');
      await expect(page.getByTestId('sector-link')).toHaveCount(6);
      expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);

      // The shell clips sideways overflow, so a header too wide for the phone shows as a cut CTA,
      // not as a scroll: measure the button against the viewport.
      const cta = await page.getByTestId('header-cta').boundingBox();

      expect(cta!.x + cta!.width).toBeLessThanOrEqual(width);

      await page.goto('/reforma/industria');
      await expectAtLeastThreeFacts(page);
      expect(await horizontalOverflow(page)).toBeLessThanOrEqual(0);
    });
  }

  test('never names the partner network or its founder', async ({ page }) => {
    await page.goto('/');
    await expect(page.getByTestId('sector-link')).toHaveCount(6);

    const text = await page.locator('body').innerText();

    expect(text).not.toContain('Willy');
    expect(text).not.toContain('APW');
  });
});
