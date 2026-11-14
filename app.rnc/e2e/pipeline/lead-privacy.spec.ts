import type { Page } from '@playwright/test';
import { expect, mockUser, seedAuthSession, seedLanguage, test } from '../fixtures/auth';

/*
 * rnc-4 R39 (F9b): on the lead detail the firm's manager (`leads.update` + `firm.settings`) downloads
 * the lead's data as JSON and anonymises it behind a confirmation that says it cannot be undone; the
 * detail then shows the anonymised state. The team (`leads.update` only) sees neither button.
 */
const MANAGER = {
  ...mockUser,
  name: 'Helena',
  last_name: 'Prado',
  username: 'helena.alvorada',
  role: 'Gestão',
  role_slug: 'gestao',
  firm: { modules: [], name: 'Alvorada Contábil', slug: 'alvorada' },
  permissions: ['leads.read', 'leads.update', 'firm.settings'],
};

const TEAM = {
  ...MANAGER,
  username: 'equipe.alvorada',
  role_slug: 'equipe',
  permissions: ['leads.read', 'leads.update'],
};

const LEAD = {
  brand: null,
  channel: 'formulario',
  cnpj: '90001371000159',
  company: 'Padaria Trigo Dourado',
  consent: null,
  contact_name: 'Ana Almeida',
  created_at: '2026-11-10T09:12:00-03:00',
  diagnosis: { items: [], opportunities: 0, risks: 0 },
  email: 'ana@padaria.exemplo.com.br',
  id: 7,
  last_step: 'resultado',
  lost_reason: null,
  medium: null,
  meeting: null,
  notes: [],
  path: 'cnpj',
  phone: '5511900000001',
  profile: { b2b: 80, band: 'b2', exporta: false, incentivos: false, regime: 'simples', sector: 'comercio' },
  source: 'organico',
  stage: 'lead',
  timeline: [
    { at: '2026-11-10T09:12:00-03:00', data: { path: 'cnpj' }, id: 1, type: 'diagnostico.inicio', user: null },
  ],
  updated_at: '2026-11-12T10:00:00-03:00',
  anonymised_at: null,
};

const ANONYMISED = {
  ...LEAD,
  cnpj: null,
  company: null,
  contact_name: null,
  email: null,
  phone: null,
  anonymised_at: '2026-11-14T15:00:00-03:00',
  timeline: [
    {
      at: '2026-11-14T15:00:00-03:00',
      data: { via: 'pedido' },
      id: 9,
      type: 'dados.anonimizados',
      user: { id: 4, name: 'Helena Prado' },
    },
    ...LEAD.timeline,
  ],
};

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

async function openLead(page: Page, user: typeof MANAGER, anonymised: string[] = []) {
  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: user })));
  await page.route(/\/api\/leads\/7$/, (route) => route.fulfill(json({ data: LEAD })));
  await page.route(/\/api\/leads\/7\/export$/, (route) =>
    route.fulfill(json({ data: { lead: { id: 7, company: LEAD.company }, consents: [], timeline: [] } }))
  );
  await page.route(/\/api\/leads\/7\/anonymise$/, (route) => {
    anonymised.push(route.request().method());

    return route.fulfill(json({ data: ANONYMISED }));
  });
  await page.goto('/equipe/leads/7');
  await expect(page.getByTestId('lead-detail')).toBeVisible();
}

test('the manager downloads the lead data as a JSON file', async ({ page }) => {
  await openLead(page, MANAGER);

  const download = page.waitForEvent('download');

  await page.getByTestId('lead-export').click();

  expect((await download).suggestedFilename()).toBe('lead-7-dados.json');
});

test('the manager anonymises the lead after a confirmation that it cannot be undone', async ({ page }) => {
  const anonymised: string[] = [];

  /*
   * Tall enough that the detail never scrolls: Playwright scrolls a control into view before clicking
   * it, the scroll lands after the popup opened, and PrimeVue's popup closes on any scroll of its
   * anchor's container — a person clicks a button already on screen.
   */
  await page.setViewportSize({ width: 1440, height: 1600 });
  await openLead(page, MANAGER, anonymised);
  await page.getByTestId('lead-anonymise').click();

  const popup = page.locator('.p-confirmpopup');

  await expect(popup).toContainText('não pode ser desfeito');
  expect(anonymised).toEqual([]);

  await popup.getByRole('button', { name: 'Anonimizar' }).click();

  await expect(page.getByTestId('lead-anonymised')).toBeVisible();
  await expect(page.getByTestId('lead-anonymise')).toHaveCount(0);
  await expect(page.getByTestId('lead-contact')).not.toContainText('Ana Almeida');
  await expect(page.getByTestId('timeline-item').first()).toHaveAttribute('data-type', 'dados.anonimizados');
  expect(anonymised).toEqual(['POST']);
});

test('the team sees neither the export nor the anonymise button', async ({ page }) => {
  await openLead(page, TEAM);

  await expect(page.getByTestId('lead-export')).toHaveCount(0);
  await expect(page.getByTestId('lead-anonymise')).toHaveCount(0);
});
