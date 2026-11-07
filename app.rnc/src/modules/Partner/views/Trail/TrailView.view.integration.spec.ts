import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { MSlaChip, SLA_STATE } from '@PortalModule';
import { REFERRAL_FIXTURE } from '../../test/fixtures';
import TrailView from './TrailView.view.vue';

/*
 * The trail (rnc-1 F6, R23): every referral, newest first — the lead, the partner, the consent as it
 * was given, who and when — and the filter by partner, which asks the API again from page 1.
 * rnc-2 F3 (R10, A8): each row shows where the partner took it — status, SLA, the value once closed.
 */
const requested: URL[] = [];

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const OLDER = {
  ...REFERRAL_FIXTURE,
  at: '2026-11-18T15:00:00-03:00',
  deal_value_cents: 1_200_000,
  id: 40,
  sla: { hours: 30, state: SLA_STATE.FORA },
  status: 'fechado',
  status_at: '2026-11-20T15:00:00+00:00',
  lead: { company: 'Padaria Trigo Dourado', id: 2 },
  partner: { active: false, id: 3, name: 'NexoERP (exemplo)', specialty: 'erp_nfe' },
};

function renderTrail() {
  return renderWithPlugins(TrailView, {
    global: {
      components: { Button, MSlaChip, Skeleton },
      stubs: {
        OPaginator: { props: ['total'], template: '<div data-testid="paginator">{{ total }}</div>' },
        // PrimeVue's Select opens an overlay jsdom cannot drive: a native one with the same contract.
        Select: {
          emits: ['update:modelValue'],
          props: ['modelValue', 'options'],
          template:
            '<select data-testid="trail-filter" @change="$emit(\'update:modelValue\', $event.target.value ? Number($event.target.value) : null)"><option value=""></option><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
        },
        RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' },
      },
    },
  });
}

beforeEach(() => {
  requested.length = 0;
  server.use(
    http.get('http://localhost/referrals', ({ request }) => {
      const url = new URL(request.url);
      const partnerId = url.searchParams.get('partner_id');
      const rows = partnerId
        ? [OLDER].filter((row) => String(row.partner.id) === partnerId)
        : [REFERRAL_FIXTURE, OLDER];

      requested.push(url);

      return HttpResponse.json({ data: { current_page: 1, data: rows, per_page: 20, total: rows.length } });
    }),
    http.get('http://localhost/partners/options', () =>
      HttpResponse.json({
        data: [
          { active: true, label: 'Margem Certa (exemplo)', value: 7 },
          { active: false, label: 'NexoERP (exemplo)', value: 3 },
        ],
      })
    )
  );
});

describe('TrailView', () => {
  it('lists every referral with the consent, who referred and when', async () => {
    renderTrail();

    const rows = await screen.findAllByTestId('trail-row');

    expect(rows).toHaveLength(2);
    expect(within(rows[0]!).getByTestId('trail-company').textContent).toContain('Consultoria Prisma');
    expect(within(rows[0]!).getByTestId('trail-partner').textContent).toContain('Margem Certa (exemplo)');
    expect(within(rows[0]!).getByTestId('trail-consent').textContent).toContain(
      'Autorizo a Franco a me apresentar a parceiros.'
    );
    expect(within(rows[0]!).getByTestId('trail-by').textContent).toContain('Patrícia Gomes');
    expect(within(rows[1]!).getByTestId('trail-partner').textContent).toContain('NexoERP (exemplo)');
  });

  it('filters by partner from page 1', async () => {
    renderTrail();

    await screen.findAllByTestId('trail-row');
    await waitFor(() => expect(screen.getByTestId('trail-filter').querySelectorAll('option')).toHaveLength(3));
    await fireEvent.update(screen.getByTestId('trail-filter'), '3');

    await waitFor(() => expect(requested[requested.length - 1]?.searchParams.get('partner_id')).toBe('3'));
    expect(requested[requested.length - 1]?.searchParams.get('page')).toBe('1');
    await waitFor(() => expect(screen.getAllByTestId('trail-row')).toHaveLength(1));
  });

  it('says so when the trail is empty', async () => {
    server.use(
      http.get('http://localhost/referrals', () =>
        HttpResponse.json({ data: { current_page: 1, data: [], per_page: 20, total: 0 } })
      )
    );
    renderTrail();

    expect(await screen.findByTestId('trail-empty')).toBeTruthy();
  });

  it("shows each referral's status and SLA, and the contract value once closed (A8, R10)", async () => {
    renderTrail();

    const rows = await screen.findAllByTestId('trail-row');

    expect(within(rows[0]!).getByTestId('trail-status').textContent).toContain('Sent');
    expect(within(rows[0]!).getByTestId('sla-chip').dataset.sla).toBe('ok');
    expect(within(rows[0]!).queryByTestId('trail-value')).toBeNull();
    expect(within(rows[1]!).getByTestId('trail-status').textContent).toContain('Closed');
    expect(within(rows[1]!).getByTestId('sla-chip').dataset.sla).toBe('fora');
    expect(within(rows[1]!).getByTestId('trail-value').textContent).toMatch(/R\$\s12\.000,00/);
  });
});
