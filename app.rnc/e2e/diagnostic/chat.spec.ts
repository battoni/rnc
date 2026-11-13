import type { Page } from '@playwright/test';
import { expect, seedLanguage, test } from '../fixtures/auth';

/*
 * The chat receptionist (rnc-2 F5, R12–R14) with the API mocked: the walk from the first question
 * to the locked result and the slot picker, typed words and quick replies mixed; the "Não entendi…"
 * of a line that matches nothing; the switch to the form mid-way; and the transcript's contrast in
 * both schemes. No result-count claim against the form here — the API is mocked, so that parity is
 * checked live in Verify's Chrome round (plan AM8). Measured in Portuguese.
 */
const TOKEN = 'c'.repeat(48);
const CONSENT = 'Autorizo a Franco a me apresentar a especialistas da Rede de Especialistas Franco quando o diagnóstico indicar.';
const PROFILE = { b2b: null, band: null, exporta: false, incentivos: false, regime: null, sector: null };
const LEAD = { channel: 'chat', cnpj: null, company: null, consent: { text: CONSENT, version: 1 }, has_contact: false, has_email: false, last_step: 'empresa', path: 'quiz', profile: PROFILE, stage: 'visitante' };

const RESULT = {
  company: 'Clínica Horizonte',
  opportunities: 1,
  risks: 1,
  items: [
    { kind: 'oportunidade', title: 'Opção pelo regime regular do IBS/CBS', range_min: 18000, range_max: 54000, range_label: 'R$ 18–54 mil/ano' },
    { kind: 'risco', title: 'Clientes PJ podem preferir fornecedores que geram crédito', range_min: 36000, range_max: 90000, range_label: 'R$ 36–90 mil/ano' },
  ],
};

const SIMULATION = {
  estimate: true,
  split_payment: { label: 'R$ 16–28 mil/mês', max: 27825, min: 15900 },
  taxes: [{ label: 'CBS', leaving: false, tax: 'cbs' }],
  years: [2026, 2027, 2028, 2029, 2030, 2031, 2032, 2033].map((year) => ({ parts: [{ label: 'CBS', rate: 9, tax: 'cbs' }], total: 9, year })),
};

const DAYS = ['2026-11-23', '2026-11-24', '2026-11-25'];

const AVAILABILITY = {
  slot_minutes: 30,
  specialists: [
    {
      area: 'Contábil · Simples Nacional',
      days: DAYS.map((date, index) => ({ date, slots: [`${date}T10:00:00-03:00`, `${date}T14:30:00-03:00`], weekday: index + 1 })),
      id: 3,
      name: 'Juliana Reis',
      suggested: true,
    },
  ],
  suggested_specialist_id: 3,
  timezone: 'America/Sao_Paulo',
};

/** Every body the page sent, by path, so a test can read what the chat saved. */
async function mockApi(page: Page) {
  const sent: { body: Record<string, unknown>; method: string; url: string }[] = [];
  const json = (data: unknown, status = 200) => ({ status, contentType: 'application/json', body: JSON.stringify(data) });

  await page.route('**/public/firm', (route) =>
    route.fulfill(json({ data: { name: 'Franco Contabilidade', phone: '11 3732-5500', phone_href: 'tel:+551137325500', mailbox: 'saf@franco-rnc.com.br', business_hours: [] } }))
  );
  await page.route('**/public/events', (route) => route.fulfill({ status: 201, contentType: 'application/json', body: '[]' }));
  await page.route('**/public/diagnostics', (route) => {
    sent.push({ body: route.request().postDataJSON(), method: 'POST', url: 'start' });

    return route.fulfill(json({ data: { ...LEAD, token: TOKEN } }, 201));
  });
  await page.route(`**/public/diagnostics/${TOKEN}`, (route) => {
    sent.push({ body: route.request().postDataJSON(), method: 'PATCH', url: 'update' });

    return route.fulfill(json({ data: LEAD }));
  });
  await page.route(`**/public/diagnostics/${TOKEN}/result`, (route) => route.fulfill(json({ data: RESULT })));
  await page.route(`**/public/diagnostics/${TOKEN}/simulation`, (route) => route.fulfill(json({ data: SIMULATION })));
  await page.route(`**/public/diagnostics/${TOKEN}/availability`, (route) => route.fulfill(json({ data: AVAILABILITY })));

  return sent;
}

async function openChat(page: Page) {
  await page.goto('/diagnostico');
  await page.getByTestId('mode-chat').click();
  await expect(page.getByTestId('chat-live')).toContainText('você tem o CNPJ');
}

/** Waits for the reception to ask, then taps the quick reply. */
async function tap(page: Page, value: string) {
  const reply = page.locator(`[data-testid="chat-reply"][data-value="${value}"]`);

  await expect(reply).toBeVisible();
  await expect(reply).not.toHaveAttribute('data-click-guard', 'locked');
  await reply.click();
}

async function say(page: Page, text: string) {
  const box = page.getByTestId('chat-input');

  await expect(box).toBeEnabled();
  await box.fill(text);
  // A person takes longer than the ClickGuard's 350 ms to type a line; Enter clicks the send button.
  await expect(page.getByTestId('chat-send')).not.toHaveAttribute('data-click-guard', 'locked');
  await box.press('Enter');
}

async function turnIs(page: Page, turn: string) {
  await expect(page.getByTestId('chat')).toHaveAttribute('data-turn', turn);
}

test.beforeEach(async ({ page }) => {
  await seedLanguage(page, 'pt-BR');
});

test('walks the chat to the locked result and the slot picker, as a chat lead', async ({ page }) => {
  const sent = await mockApi(page);

  await openChat(page);
  await say(page, 'não tenho');
  await turnIs(page, 'sector');
  await say(page, 'Serviços');
  await turnIs(page, 'regime');
  await tap(page, 'simples');
  await turnIs(page, 'company');
  await say(page, 'Clínica Horizonte');
  await say(page, 'Ana Souza');
  await turnIs(page, 'contact');
  await say(page, '(11) 98888-7777');
  await turnIs(page, 'consent');
  await expect(page.getByTestId('chat-live')).toContainText(CONSENT);
  await tap(page, 'autorizo');
  await turnIs(page, 'b2b');
  await say(page, '80%');
  await turnIs(page, 'band');
  await tap(page, 'b2');
  await tap(page, 'nao');
  await tap(page, 'nao');

  const result = page.getByTestId('chat-result');

  await expect(result.getByTestId('result-summary')).toHaveText('Encontramos 1 oportunidade e 1 risco');
  await expect(result.getByTestId('locked')).toHaveCount(2);
  await expect(page.getByTestId('specialist')).toHaveCount(1);
  await expect(page.getByTestId('slot').first()).toBeVisible();

  expect(sent.find((request) => request.url === 'start')?.body).toMatchObject({ channel: 'chat', path: 'quiz' });
  expect(sent.filter((request) => request.method === 'PATCH').map((request) => request.body.step)).toEqual([
    'contato',
    'perfil',
    'faturamento',
    'resultado',
  ]);
});

test('says "Não entendi…" to a line that matches no option, and keeps the question', async ({ page }) => {
  await mockApi(page);

  await openChat(page);
  await tap(page, 'quiz');
  await turnIs(page, 'sector');
  await say(page, 'não sei de nada');

  await expect(page.getByRole('log').getByTestId('chat-error')).toHaveText(
    /Não entendi\. Escolha uma das opções abaixo\./
  );
  // The log is the live region: no alert inside it, or a screen reader says the line twice.
  await expect(page.getByRole('alert')).toHaveCount(0);
  await turnIs(page, 'sector');

  await say(page, 'saude');

  await turnIs(page, 'regime');
  await expect(page.getByTestId('chat-error')).toHaveCount(0);
});

test('keeps the answers when the visitor switches to the form mid-way', async ({ page }) => {
  await mockApi(page);

  await openChat(page);
  await tap(page, 'quiz');
  await tap(page, 'servicos');
  await tap(page, 'simples');
  await turnIs(page, 'company');
  await say(page, 'Clínica Horizonte');
  await turnIs(page, 'name');

  await page.getByTestId('mode-form').click();

  await expect(page.getByTestId('contact-company')).toHaveValue('Clínica Horizonte');

  await page.getByTestId('mode-chat').click();

  await turnIs(page, 'name');
  await expect(page.getByTestId('chat-log')).toContainText('Clínica Horizonte');
});

for (const scheme of ['light', 'dark'] as const) {
  test(`the transcript reads at 4.5:1 or more, ${scheme}`, async ({ page }) => {
    await mockApi(page);
    await page.emulateMedia({ colorScheme: scheme, reducedMotion: 'reduce' });

    await openChat(page);
    await tap(page, 'quiz');
    await say(page, 'não sei de nada');
    await expect(page.getByTestId('chat-error')).toBeVisible();

    const ratios = await page.evaluate((schemeName) => {
      document.documentElement.setAttribute('data-theme', schemeName);

      const channel = (value: number) => {
        const srgb = value / 255;

        return srgb <= 0.03928 ? srgb / 12.92 : ((srgb + 0.055) / 1.055) ** 2.4;
      };
      const luminance = (colour: string) => {
        const [r, g, b] = (colour.match(/\d+(\.\d+)?/g) ?? []).slice(0, 3).map(Number);

        return 0.2126 * channel(r!) + 0.7152 * channel(g!) + 0.0722 * channel(b!);
      };
      const groundOf = (element: Element): string => {
        for (let node: Element | null = element; node; node = node.parentElement) {
          const background = getComputedStyle(node).backgroundColor;

          if (!/rgba\(.*,\s*0\)$/.test(background) && background !== 'transparent') return background;
        }

        return 'rgb(255, 255, 255)';
      };
      const texts = document.querySelectorAll(
        '[data-testid="chat-msg"] span, [data-testid="chat-live"] span, [data-testid="chat-reply"]'
      );

      return Array.from(texts)
        .filter((element) => element.textContent?.trim())
        .map((element) => {
          const [lighter, darker] = [luminance(getComputedStyle(element).color), luminance(groundOf(element))].sort(
            (a, b) => b - a
          );

          return { ratio: (lighter! + 0.05) / (darker! + 0.05), text: element.textContent!.trim().slice(0, 30) };
        });
    }, scheme);

    expect(ratios.length).toBeGreaterThan(4);

    for (const { ratio, text } of ratios) {
      expect(ratio, `"${text}"`).toBeGreaterThanOrEqual(4.5);
    }
  });
}

test('fits a 390 px phone without a sideways scroll, replies and box included', async ({ page }) => {
  await mockApi(page);
  await page.setViewportSize({ width: 390, height: 844 });

  await openChat(page);
  await tap(page, 'quiz');
  await turnIs(page, 'sector');
  await expect(page.getByTestId('chat-reply')).toHaveCount(6);

  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  const box = await page.getByTestId('chat-compose').boundingBox();

  expect(overflow).toBeLessThanOrEqual(0);
  expect(box!.x + box!.width).toBeLessThanOrEqual(390);
});
