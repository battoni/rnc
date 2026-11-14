import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { LEDGER_MODEL } from '@PortalModule';
import { useUserStore } from '@UserModule';
import { NETWORK_LEDGER_FIXTURE, NETWORK_LEDGER_SUBSCRIPTION_FIXTURE } from '../../test/fixtures';
import LedgerView from './LedgerView.view.vue';

/*
 * Reinaldo's network ledger (rnc-2 F4, R11, A9's Vitest half): every partner's month, the total
 * under the network's model and what the other would give, every figure "valor a conferir — sem
 * cobrança automática". The model switch (`partners.manage`) moves the whole network and reloads
 * both totals; without the key the model is only named. ◀ ▶ ask the API for another month.
 */
const requested: (string | null)[] = [];
const switched: unknown[] = [];
let model: LEDGER_MODEL = LEDGER_MODEL.COMISSAO;

function renderLedger(permissions: string[] = ['ledger.read', 'partners.manage']) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 2, last_name: 'Franco', name: 'Reinaldo', role: 'CEO', role_slug: 'gestao', username: 'reinaldo' },
    permissions
  );

  return renderWithPlugins(LedgerView, { global: { components: { Button, Skeleton } }, pinia });
}

beforeEach(() => {
  requested.length = 0;
  switched.length = 0;
  model = LEDGER_MODEL.COMISSAO;
  server.use(
    http.get('http://localhost/ledger', ({ request }) => {
      const month = new URL(request.url).searchParams.get('month');
      const answer = model === LEDGER_MODEL.COMISSAO ? NETWORK_LEDGER_FIXTURE : NETWORK_LEDGER_SUBSCRIPTION_FIXTURE;

      requested.push(month);

      return HttpResponse.json({ data: { ...answer, ...(month ? { month } : {}) } });
    }),
    http.patch('http://localhost/ledger/model', async ({ request }) => {
      const body = (await request.json()) as { model: LEDGER_MODEL };

      switched.push(body);
      model = body.model;

      return HttpResponse.json({ data: { commission_rate_bp: 1000, model, monthly_fee_cents: 49_000 } });
    })
  );
});

describe('LedgerView', () => {
  it("lists every partner's month and what each owes under comissão (A9)", async () => {
    renderLedger();

    const rows = await screen.findAllByTestId('ledger-row');

    expect(rows).toHaveLength(2);
    expect(rows[1]?.textContent).toContain('NexoERP (exemplo)');
    expect(rows[1]?.textContent).toMatch(/R\$\s36\.000,00/);
    expect(rows[1]?.textContent).toMatch(/R\$\s3\.600,00/);
    expect(screen.getByTestId('ledger-total').textContent).toMatch(/R\$\s3\.600,00/);
    expect(screen.getByTestId('ledger-other').textContent).toMatch(/R\$\s980,00/);
  });

  it('labels every amount "to be checked — no automatic billing"', async () => {
    renderLedger();

    expect((await screen.findByTestId('ledger-notice')).textContent).toContain('to be checked — no automatic billing');
  });

  it('switches the whole network to assinatura and reloads both totals (A9)', async () => {
    renderLedger();

    const group = await screen.findByRole('group', { name: 'Billing model' });
    const subscription = within(group).getByRole('button', { name: 'Monthly subscription' });

    expect(within(group).getByRole('button', { name: 'Commission per closing' }).getAttribute('aria-pressed')).toBe(
      'true'
    );

    await fireEvent.click(subscription);

    await waitFor(() => expect(screen.getByTestId('ledger-total').textContent).toMatch(/R\$\s980,00/));

    expect(screen.getByTestId('ledger-other').textContent).toMatch(/R\$\s3\.600,00/);
    expect(subscription.getAttribute('aria-pressed')).toBe('true');
    expect(switched).toEqual([{ model: 'assinatura' }]);
    expect(requested).toEqual([null, '2026-11']);
  });

  it('only names the model without partners.manage', async () => {
    renderLedger(['ledger.read']);

    await screen.findAllByTestId('ledger-row');

    expect(screen.queryByRole('group', { name: 'Billing model' })).toBeNull();
    expect(screen.getByTestId('ledger-model').textContent).toContain('Commission per closing');
  });

  it('asks for the month before', async () => {
    renderLedger();

    await screen.findAllByTestId('ledger-row');
    await fireEvent.click(screen.getByRole('button', { name: 'Previous month' }));

    await waitFor(() => expect(requested).toEqual([null, '2026-10']));
    await waitFor(() => expect(screen.getByTestId('ledger-month').textContent).toContain('October 2026'));
  });
});
