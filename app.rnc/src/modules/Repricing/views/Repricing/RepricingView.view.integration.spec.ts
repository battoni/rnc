import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import { MRepricingDetail, MRepricingSpread, MRepricingTable } from '../../components';
import { COMERCIO_FIXTURE, DETAIL_FIXTURE, EMPORIO, LIST_FIXTURE } from '../../test/fixtures';
import RepricingView from './RepricingView.view.vue';

/*
 * Reinaldo's fee repricing (rnc-1 F8, R26): the yearly uplift over the listed clients, the spread by
 * band, the sector and regime filters that ask the API again, a client's items, the letter link and
 * the batch mark. The CSV import is offered only with `clients.import`.
 */
const requested: URL[] = [];
const marked: unknown[] = [];

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  MImportClientsForm: { template: '<div data-testid="import-form" />' },
  MMainDialog: { props: ['visible'], template: '<div v-if="visible" data-testid="dialog"><slot /></div>' },
  RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' },
  // PrimeVue's Select opens an overlay jsdom cannot drive: a native one with the same contract.
  Select: {
    emits: ['update:modelValue'],
    props: ['inputId', 'modelValue', 'options'],
    template:
      '<select :data-testid="inputId" @change="$emit(\'update:modelValue\', $event.target.value || null)"><option value=""></option><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
  },
};

function renderRepricing(permissions: string[] = ['repricing.manage', 'clients.import']) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 2, last_name: 'Franco', name: 'Reinaldo', role: 'CEO', role_slug: 'ceo', username: 'reinaldo' },
    permissions
  );

  return renderWithPlugins(RepricingView, {
    global: { components: { Button, MRepricingDetail, MRepricingSpread, MRepricingTable, Skeleton }, stubs: STUBS },
    pinia,
  });
}

beforeEach(() => {
  requested.length = 0;
  marked.length = 0;
  server.use(
    http.get('http://localhost/repricing', ({ request }) => {
      const url = new URL(request.url);

      requested.push(url);

      return HttpResponse.json({
        data: url.searchParams.get('sector') === 'comercio' ? COMERCIO_FIXTURE : LIST_FIXTURE,
      });
    }),
    http.get('http://localhost/repricing/1', () => HttpResponse.json({ data: DETAIL_FIXTURE })),
    http.post('http://localhost/repricing/mark', async ({ request }) => {
      const body = (await request.json()) as { client_ids: number[] };

      marked.push(body);

      return HttpResponse.json({
        data: {
          clients: LIST_FIXTURE.clients
            .filter((client) => body.client_ids.includes(client.id))
            .map((client) => ({
              ...client,
              repricing: {
                at: '2026-11-19T13:00:00+00:00',
                by: 2,
                by_name: 'Reinaldo Franco',
                pct: client.pct,
                suggested_cents: client.suggested_cents,
              },
            })),
          marked: body.client_ids.length,
        },
      });
    })
  );
});

describe('RepricingView', () => {
  it('shows the yearly uplift of the listed clients, the KPIs and the spread', async () => {
    renderRepricing();

    const total = await screen.findByTestId('repricing-total');

    expect(total.getAttribute('data-value')).toBe('300000');
    expect(total.textContent).toContain('3.000,00');
    expect(screen.getAllByTestId('repricing-row')).toHaveLength(2);
    expect(screen.getByTestId('repricing-marked-count').textContent).toContain('1');
    expect(screen.getAllByTestId('spread-band').map((band) => band.getAttribute('data-count'))).toEqual([
      '1',
      '1',
      '0',
      '0',
    ]);
  });

  it('asks the API again when the sector changes, and the total follows', async () => {
    renderRepricing();

    await screen.findAllByTestId('repricing-row');
    await fireEvent.update(screen.getByTestId('repricing-sector'), 'comercio');

    await waitFor(() => expect(screen.getAllByTestId('repricing-row')).toHaveLength(1));

    expect(requested[requested.length - 1]?.searchParams.get('sector')).toBe('comercio');
    expect(screen.getByTestId('repricing-total').getAttribute('data-value')).toBe('60000');
  });

  it('opens a client with its items and the link to its letter', async () => {
    renderRepricing();

    const [first] = await screen.findAllByTestId('repricing-open');

    await fireEvent.click(first!);

    const detail = await screen.findByTestId('repricing-detail');
    const items = await within(detail).findAllByTestId('repricing-item');

    expect(items.map((item) => item.getAttribute('data-kind'))).toEqual(['oportunidade', 'risco']);
    expect(within(detail).getByTestId('repricing-letter-link').getAttribute('data-to')).toContain('"id":1');
  });

  it('marks the selected clients in batch and shows them marked', async () => {
    renderRepricing();

    const boxes = await screen.findAllByTestId('repricing-select');
    const markButton = screen.getByTestId('repricing-mark');

    expect(markButton.hasAttribute('disabled')).toBe(true);

    await fireEvent.click(boxes[0]!);
    await fireEvent.click(markButton);

    await waitFor(() => expect(marked).toEqual([{ client_ids: [1] }]));
    await waitFor(() => expect(screen.getAllByTestId('repricing-marked')).toHaveLength(2));

    expect(screen.getByTestId('repricing-marked-count').textContent).toContain('2');
  });

  it('selects every listed client at once', async () => {
    renderRepricing();

    await screen.findAllByTestId('repricing-row');
    await fireEvent.click(screen.getByTestId('repricing-select-all'));
    await fireEvent.click(screen.getByTestId('repricing-mark'));

    await waitFor(() => expect(marked).toEqual([{ client_ids: [1, EMPORIO.id] }]));
  });

  it('offers the CSV import only to who holds clients.import', async () => {
    const { unmount } = renderRepricing(['repricing.manage']);

    await screen.findAllByTestId('repricing-row');

    expect(screen.queryByTestId('repricing-import')).toBeNull();

    unmount();
    renderRepricing();

    await fireEvent.click(await screen.findByTestId('repricing-import'));

    expect(screen.getByTestId('import-form')).toBeTruthy();
  });

  it('says the portfolio did not load, and offers to try again', async () => {
    server.use(http.get('http://localhost/repricing', () => HttpResponse.json({ message: 'boom' }, { status: 500 })));

    renderRepricing();

    expect(await screen.findByRole('alert')).toBeTruthy();
  });
});
