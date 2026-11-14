import { expect, mockUser, seedAuthSession, seedLanguage, test } from '../fixtures/auth';
import { emulateDarkPrint, readPrintedSheet } from '../fixtures/print';

/*
 * The letter of repricing (rnc-1 R26) is what Reinaldo sends a client, printed or saved as PDF.
 * The screen may be in the dark scheme; the paper is white either way. In print media the letter
 * must be a light document: every line dark enough to read on white, the sheet's own fill light,
 * the root's `color-scheme` light — and the screen keeps its dark scheme.
 */
const CEO = {
  ...mockUser,
  name: 'Reinaldo',
  last_name: 'Franco',
  username: 'reinaldo',
  role: 'CEO',
  role_slug: 'gestao',
  permissions: ['repricing.manage', 'clients.import'],
};

const LETTER = {
  client: { cnpj: '99900001999901', id: 1, name: 'Consultoria Aurora Ltda' },
  current_cents: 200_000,
  effective_from: '2027-01-01',
  firm: {
    address: 'Rua do Exemplo, 100 · São Paulo-SP',
    mailbox: 'contato@franco.exemplo',
    name: 'Franco Contabilidade',
    phone: '(11) 3000-0000',
  },
  issued_on: '2026-11-19',
  items: [
    { kind: 'oportunidade', title: 'Crédito do Simples' },
    { kind: 'risco', title: 'Split payment no caixa' },
  ],
  pct: 10,
  suggested_cents: 220_000,
};

function json(body: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(body) };
}

test.beforeEach(async ({ page }) => {
  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: CEO })));
  await page.route('**/repricing/1/letter', (route) => route.fulfill(json({ data: LETTER })));
});

test('prints the letter as a light document while the screen is dark', async ({ page }) => {
  await page.emulateMedia({ colorScheme: 'dark' });
  await page.goto('/gestao/reajuste/1/carta');
  await expect(page.getByTestId('repricing-letter')).toBeVisible();

  // The screen is in the dark scheme, and stays so.
  await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

  await emulateDarkPrint(page);

  const sheet = await readPrintedSheet(page, '[data-testid="repricing-letter"]');

  expect(sheet.measured).toBeGreaterThan(10);
  expect(sheet.faintText).toEqual([]);
  expect(sheet.sheetLuminance === null || sheet.sheetLuminance > 0.8).toBe(true);
  expect(sheet.rootColorScheme).toBe('light');

  // Back on screen the dark canvas is untouched.
  await page.emulateMedia({ media: 'screen' });
  await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(10, 10, 9)');
});
