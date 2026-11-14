import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import { MClientTimeline, MSupplierCreditRow } from '../../components';
import { ATACADO, REVIEW_EVENT, SUPPLIER_FIXTURE, SUPPLIER_SAUDE_FIXTURE } from '../../test/fixtures';
import SupplierCreditView from './SupplierCreditView.view.vue';

/*
 * "Crédito de fornecedores" (rnc-2 F8, R21–R22, A17's Vitest half): the KPIs over the listed clients,
 * the sector filter that asks the API again (the KPIs follow it), one row per client with its
 * purchases split Simples · regime normal and the credit lost per year as a range labelled an
 * estimate; "Propor revisão" — disabled at 0 % — writes one timeline line and the row then reads
 * "Revisão proposta".
 */
const requested: URL[] = [];
const reviewed: string[] = [];

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  // PrimeVue's Select opens an overlay jsdom cannot drive: a native one with the same contract.
  Select: {
    emits: ['update:modelValue'],
    props: ['inputId', 'modelValue', 'options'],
    template:
      '<select :data-testid="inputId" @change="$emit(\'update:modelValue\', $event.target.value || null)"><option value=""></option><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
  },
};

function renderSuppliers() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 2, last_name: 'Franco', name: 'Reinaldo', role: 'CEO', role_slug: 'gestao', username: 'reinaldo' },
    ['leads.read', 'fiscal.read']
  );

  return renderWithPlugins(SupplierCreditView, {
    global: { components: { Button, MClientTimeline, MSupplierCreditRow, Skeleton }, stubs: STUBS },
    pinia,
  });
}

function rowOf(id: number): HTMLElement {
  return screen.getAllByTestId('supplier-row').find((row) => row.getAttribute('data-id') === String(id))!;
}

beforeEach(() => {
  requested.length = 0;
  reviewed.length = 0;
  server.use(
    http.get('http://localhost/fiscal/supplier-credit', ({ request }) => {
      const url = new URL(request.url);

      requested.push(url);

      return HttpResponse.json({
        data: url.searchParams.get('sector') === 'saude' ? SUPPLIER_SAUDE_FIXTURE : SUPPLIER_FIXTURE,
      });
    }),
    http.post('http://localhost/fiscal/supplier-credit/:id/review', ({ params }) => {
      reviewed.push(String(params.id));

      return HttpResponse.json({ data: REVIEW_EVENT }, { status: 201 });
    }),
    http.get('http://localhost/clients/21/events', () => HttpResponse.json({ data: [REVIEW_EVENT] }))
  );
});

describe('SupplierCreditView', () => {
  it('shows the KPIs over the listed clients, the total lost per year as a range', async () => {
    renderSuppliers();

    const total = await screen.findByTestId('supplier-total');

    expect(total.getAttribute('data-low')).toBe('7740000');
    expect(total.getAttribute('data-high')).toBe('8460000');
    expect(screen.getByTestId('supplier-buying').textContent).toContain('1');
    expect(screen.getByTestId('supplier-buying').textContent).toContain('2');
    expect(screen.getByTestId('supplier-mean-share').textContent).toContain('30%');
    expect(screen.getByTestId('supplier-proposed-count').textContent).toContain('0');
    expect(screen.getAllByTestId('supplier-row').map((row) => row.getAttribute('data-id'))).toEqual(['21', '22']);
  });

  it("draws each row's purchases split Simples · regime normal, and its range as an estimate", async () => {
    renderSuppliers();

    await screen.findAllByTestId('supplier-row');

    const row = rowOf(ATACADO.id);
    const parts = within(row).getAllByTestId('supplier-part');
    const range = within(row).getByTestId('supplier-range');

    expect(parts.map((part) => [part.getAttribute('data-part'), part.getAttribute('data-share')])).toEqual([
      ['simples', '30'],
      ['normal', '70'],
    ]);
    expect(range.textContent).toMatch(/77\.400,00/);
    expect(range.textContent).toMatch(/84\.600,00/);
    expect(within(row).getByTestId('supplier-estimate')).toBeTruthy();
  });

  it('A17: disables "Propor revisão" for a client buying nothing from Simples', async () => {
    renderSuppliers();

    await screen.findAllByTestId('supplier-row');

    const button = within(rowOf(22)).getByTestId('supplier-propose');

    expect(button.hasAttribute('disabled')).toBe(true);

    await fireEvent.click(button);

    expect(reviewed).toEqual([]);
  });

  it('A17: "Propor revisão" writes one timeline line and the row reads "Revisão proposta"', async () => {
    renderSuppliers();

    await screen.findAllByTestId('supplier-row');
    await fireEvent.click(within(rowOf(ATACADO.id)).getByTestId('supplier-propose'));

    await waitFor(() => expect(within(rowOf(ATACADO.id)).getByTestId('supplier-proposed')).toBeTruthy());

    expect(reviewed).toEqual(['21']);
    expect(within(rowOf(ATACADO.id)).queryByTestId('supplier-propose')).toBeNull();
    expect(screen.getByTestId('supplier-proposed-count').textContent).toContain('1');
  });

  it("names the API's refusal on the row when nothing in the window came from Simples", async () => {
    server.use(
      http.post('http://localhost/fiscal/supplier-credit/:id/review', () =>
        HttpResponse.json({ code: 'no_simples_purchases', message: 'fiscal.noSimplesPurchases' }, { status: 422 })
      )
    );

    renderSuppliers();

    await screen.findAllByTestId('supplier-row');
    await fireEvent.click(within(rowOf(ATACADO.id)).getByTestId('supplier-propose'));

    const alert = await within(rowOf(ATACADO.id)).findByRole('alert');

    expect(alert.textContent).toContain('Simples');
    expect(within(rowOf(ATACADO.id)).queryByTestId('supplier-proposed')).toBeNull();
  });

  it('opens the timeline of a client whose review was proposed', async () => {
    renderSuppliers();

    await screen.findAllByTestId('supplier-row');
    await fireEvent.click(within(rowOf(ATACADO.id)).getByTestId('supplier-propose'));
    await fireEvent.click(await within(rowOf(ATACADO.id)).findByTestId('supplier-history'));

    const lines = await within(rowOf(ATACADO.id)).findAllByTestId('timeline-line');

    expect(lines.map((line) => line.getAttribute('data-type'))).toEqual(['fornecedores.revisao']);
  });

  it('asks the API again for a sector, and the KPIs follow it', async () => {
    renderSuppliers();

    await screen.findAllByTestId('supplier-row');
    await fireEvent.update(screen.getByTestId('supplier-sector'), 'saude');

    await waitFor(() => expect(screen.getAllByTestId('supplier-row')).toHaveLength(1));

    expect(requested[requested.length - 1]?.searchParams.get('sector')).toBe('saude');
    expect(screen.getByTestId('supplier-total').getAttribute('data-high')).toBe('0');
  });

  it('says the list did not load, and offers to try again', async () => {
    server.use(
      http.get('http://localhost/fiscal/supplier-credit', () => HttpResponse.json({ message: 'boom' }, { status: 500 }))
    );

    renderSuppliers();

    expect(await screen.findByRole('alert')).toBeTruthy();
  });
});
