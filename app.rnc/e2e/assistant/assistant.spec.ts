import type { Page } from '@playwright/test';
import { expect, mockUser, seedAuthSession, seedLanguage, test } from '../fixtures/auth';

/*
 * "Assistente da Reforma" (rnc-3 F5, R12, R13, A14), with the API mocked: Patrícia asks the CBS
 * question and reads Franco's reviewed note with its rule, source and reviewer; she asks something no
 * note covers and gets the fixed sentence and the hand-off, which goes to the specialist she picks.
 * The screen fits a phone and a wide screen with no sideways scroll, in both schemes.
 */
const PATRICIA = {
  ...mockUser,
  id: 2,
  name: 'Patrícia',
  last_name: 'Gomes',
  username: 'patricia',
  role: 'Equipe',
  role_slug: 'equipe',
  is_specialist: false,
  permissions: ['leads.read', 'agenda.read', 'assistant.ask', 'notifications.read'],
};

const MARIANA = { id: 3, name: 'Mariana Toledo' };
const CARLOS = { id: 4, name: 'Carlos Prado' };

const CBS = {
  answer: 'A CBS começa a ser cobrada em 2027, com alíquota cheia, e o PIS e a Cofins deixam de existir.',
  as_of: '2026-11-05',
  id: 1,
  question: 'Quando começa a cobrança da CBS?',
  reviewed_at: '2026-11-05T13:00:00+00:00',
  reviewer: MARIANA,
  rule: 'LC 214/2025, art. 343',
  slug: 'cbs',
  source_label: 'Barbieri Advogados — Cronograma da Reforma',
  source_url: 'https://www.barbieriadvogados.com/cronograma-reforma-tributaria',
};

function json(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

async function signedIn(page: Page, handed: unknown[] = []) {
  await seedAuthSession(page);
  await seedLanguage(page, 'pt-BR');
  await page.route('**/auth/me**', (route) => route.fulfill(json({ data: PATRICIA })));
  await page.route('**/notifications**', (route) => route.fulfill(json({ data: { items: [], unread_count: 0 } })));
  await page.route('**/assistant/suggestions', (route) =>
    route.fulfill(json({ data: [{ id: 1, question: CBS.question }] }))
  );
  await page.route('**/assistant/ask', async (route) => {
    const { question } = route.request().postDataJSON() as { question: string };
    const known = /cbs/i.test(question);

    await route.fulfill(
      json({ data: { note: known ? CBS : null, question, specialists: known ? [] : [CARLOS, MARIANA] } })
    );
  });
  await page.route('**/assistant/questions', async (route) => {
    const body = route.request().postDataJSON() as { question: string; specialist_id: number };
    handed.push(body);
    const specialist = [CARLOS, MARIANA].find((person) => person.id === body.specialist_id);

    await route.fulfill(
      json(
        {
          data: {
            answer: null,
            answered_at: null,
            asked_by: { id: 2, name: 'Patrícia Gomes' },
            created_at: '2026-11-19T13:00:00+00:00',
            id: 21,
            note_id: null,
            question: body.question,
            specialist,
            status: 'aberta',
          },
        },
        201
      )
    );
  });
}

async function ask(page: Page, question: string) {
  await page.getByTestId('assistant-input').fill(question);
  await page.getByTestId('assistant-send').click();
}

async function sidewaysScroll(page: Page): Promise<number> {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

test.describe('Assistente da Reforma (R12, R13, A14)', () => {
  for (const [width, scheme] of [
    [390, 'light'],
    [1440, 'dark'],
  ] as const) {
    test(`answers from a reviewed note with its source and reviewer, at ${width} in ${scheme}`, async ({ page }) => {
      await page.setViewportSize({ height: 900, width });
      await page.emulateMedia({ colorScheme: scheme });
      await signedIn(page);
      await page.goto('/equipe/assistente');

      await ask(page, 'quando começa a cobrança da CBS');

      const card = page.getByTestId('answer-card');

      await expect(card).toBeVisible();
      await expect(card.getByTestId('answer-rule')).toHaveText('Regra: LC 214/2025, art. 343');
      await expect(card.getByTestId('answer-source')).toContainText('lida em 05/11/2026');
      await expect(card.getByTestId('answer-reviewer')).toHaveText('revisado por Mariana Toledo em 05/11/2026');
      await expect(page.getByTestId('assistant-count')).toContainText('1 resposta nesta conversa');
      expect(await sidewaysScroll(page)).toBeLessThanOrEqual(0);
    });
  }

  test('hands an unknown question to the specialist picked', async ({ page }) => {
    const handed: unknown[] = [];

    await signedIn(page, handed);
    await page.goto('/equipe/assistente');

    await ask(page, 'qual o melhor ERP');

    const handoff = page.getByTestId('handoff-card');

    await expect(handoff).toContainText(
      'Não há nota revisada pela equipe para essa pergunta, então não vou arriscar uma resposta.'
    );
    await handoff.getByTestId('handoff-specialist').click();
    await page.getByRole('option', { name: 'Mariana Toledo' }).click();
    await handoff.getByRole('button', { name: 'Encaminhar ao especialista' }).click();

    await expect(handoff.getByTestId('handoff-done')).toHaveText(
      'Encaminhada para Mariana Toledo. A resposta chega pelo sino de notificações.'
    );
    expect(handed).toEqual([{ question: 'qual o melhor ERP', specialist_id: MARIANA.id }]);
  });

  test('finds the assistant in the team rail', async ({ page }) => {
    await signedIn(page);
    await page.goto('/equipe/assistente');

    await expect(page.getByRole('link', { name: 'Assistente da Reforma' }).first()).toBeVisible();
  });
});
