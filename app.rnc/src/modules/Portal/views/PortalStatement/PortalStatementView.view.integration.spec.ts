import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it } from 'vitest';
import type { PortalStatement } from '../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { COMMISSION_STATEMENT_FIXTURE, SUBSCRIPTION_STATEMENT_FIXTURE } from '../../test/fixtures';
import PortalStatementView from './PortalStatementView.view.vue';

/*
 * The partner's statement (rnc-2 F4, R11, A9's Vitest half): its month under the firm's model —
 * the lines, "Total devido à Franco", the model's rule and "valor a conferir — sem cobrança
 * automática" — never the other model (D10). ◀ ▶ ask the API for the month before or after; ▶ stops
 * at the month the API first answered.
 */
const requested: (string | null)[] = [];
let answer: PortalStatement | null = COMMISSION_STATEMENT_FIXTURE;

function renderStatement() {
  return renderWithPlugins(PortalStatementView, { global: { components: { Button, Skeleton } } });
}

beforeEach(() => {
  requested.length = 0;
  answer = COMMISSION_STATEMENT_FIXTURE;
  server.use(
    http.get('http://localhost/portal/statement', ({ request }) => {
      const month = new URL(request.url).searchParams.get('month');

      requested.push(month);

      return answer
        ? HttpResponse.json({ data: { ...answer, ...(month ? { month } : {}) } })
        : HttpResponse.json({ message: 'boom' }, { status: 500 });
    })
  );
});

describe('PortalStatementView', () => {
  it("shows the month's closings, what each adds and the total owed under comissão (A9)", async () => {
    renderStatement();

    const table = await screen.findByRole('table');
    const rows = within(table).getAllByTestId('statement-line');

    expect(rows).toHaveLength(2);
    expect(rows[0]?.textContent).toContain('Consultoria Prisma Ltda');
    expect(rows[0]?.textContent).toMatch(/R\$\s24\.000,00/);
    expect(rows[0]?.textContent).toMatch(/R\$\s2\.400,00/);
    expect(rows[1]?.textContent).toMatch(/R\$\s1\.200,00/);
    expect(screen.getByTestId('statement-total').textContent).toMatch(/R\$\s3\.600,00/);
    expect(screen.getByText('Total owed to Franco Contabilidade')).toBeTruthy();
    expect(screen.getByTestId('statement-rule').textContent).toContain('10% of the first-year contract value');
    expect(screen.getByTestId('statement-month').textContent).toContain('November 2026');
  });

  it('labels every amount "to be checked — no automatic billing"', async () => {
    renderStatement();

    expect((await screen.findByTestId('ledger-notice')).textContent).toContain('to be checked — no automatic billing');
  });

  it('shows the fee under assinatura, and the closings as included (A9)', async () => {
    answer = SUBSCRIPTION_STATEMENT_FIXTURE;
    renderStatement();

    const rows = await screen.findAllByTestId('statement-line');

    expect(rows[0]?.textContent).toContain('Monthly network subscription');
    expect(rows[0]?.textContent).toMatch(/R\$\s490,00/);
    expect(rows[1]?.textContent).toContain('included');
    expect(screen.getByTestId('statement-total').textContent).toMatch(/R\$\s490,00/);
    expect(screen.getByTestId('statement-rule').textContent).toMatch(/R\$\s490,00 a month/);
  });

  it('never shows the other model (D10)', async () => {
    renderStatement();

    await screen.findByRole('table');

    expect(document.body.textContent).not.toMatch(/subscription|a month|other model/i);
  });

  it('asks for the month before, and back, stopping at this month', async () => {
    renderStatement();

    await waitFor(() => expect(screen.getByTestId('statement-month').textContent).toContain('November 2026'));

    const next = screen.getByRole('button', { name: 'Next month' });

    expect(next.hasAttribute('disabled')).toBe(true);

    await fireEvent.click(screen.getByRole('button', { name: 'Previous month' }));
    await waitFor(() => expect(screen.getByTestId('statement-month').textContent).toContain('October 2026'));

    await waitFor(() => expect(next.hasAttribute('disabled')).toBe(false));

    await fireEvent.click(next);
    await waitFor(() => expect(screen.getByTestId('statement-month').textContent).toContain('November 2026'));

    expect(requested).toEqual([null, '2026-10', '2026-11']);
  });

  it('says so when the month has no closing', async () => {
    answer = { ...COMMISSION_STATEMENT_FIXTURE, deal_total_cents: 0, deals: 0, lines: [], total_cents: 0 };
    renderStatement();

    expect(await screen.findByText('No referral closed this month.')).toBeTruthy();
  });

  it('offers a retry when the statement does not load', async () => {
    answer = null;
    renderStatement();

    const alert = await screen.findByRole('alert');

    answer = COMMISSION_STATEMENT_FIXTURE;
    await fireEvent.click(within(alert).getByRole('button', { name: 'Try again' }));

    expect(await screen.findByRole('table')).toBeTruthy();
  });
});
