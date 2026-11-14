import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import MAddEditPartnerForm from '../../components/molecules/MAddEditPartnerForm/MAddEditPartnerForm.vue';
import MPartnerAccessForm from '../../components/molecules/MPartnerAccessForm/MPartnerAccessForm.vue';
import { PARTNER_ACCESS_FIXTURE, PARTNER_FIXTURE } from '../../test/fixtures';
import PartnersView from './PartnersView.view.vue';

/*
 * The partner registry (rnc-1 F6, R21) for Reinaldo and the operator: the list, a new partner from
 * the dialog, and the deactivation — each answered by the API and applied to the row, no refetch.
 */
const created: Record<string, unknown>[] = [];
const patched: Record<string, unknown>[] = [];

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  APageHeaderAction: {
    emits: ['click'],
    props: ['label'],
    template: '<button data-testid="add-partner" @click="$emit(\'click\')">{{ label }}</button>',
  },
  ConfirmPopup: true,
  MMainDialog: { props: ['visible'], template: '<div v-if="visible" data-testid="dialog"><slot /></div>' },
  // A native select with Select's contract: jsdom cannot open PrimeVue's overlay.
  Select: {
    emits: ['update:modelValue'],
    props: ['inputId', 'modelValue', 'options'],
    template:
      '<select :id="inputId" data-testid="partner-specialty" :value="modelValue" @change="$emit(\'update:modelValue\', $event.target.value)"><option value=""></option><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
  },
};

function renderPartners() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 2, last_name: 'Franco', name: 'Reinaldo', role: 'CEO', role_slug: 'gestao', username: 'reinaldo' },
    ['partners.manage', 'referrals.read', 'metrics.read', 'leads.read']
  );

  return renderWithPlugins(PartnersView, {
    global: { components: { Button, InputText, MAddEditPartnerForm, MPartnerAccessForm, Skeleton }, stubs: STUBS },
    pinia,
  });
}

beforeEach(() => {
  created.length = 0;
  patched.length = 0;
  server.use(
    http.get('http://localhost/partners', () =>
      HttpResponse.json({
        data: [
          structuredClone(PARTNER_FIXTURE),
          {
            ...PARTNER_FIXTURE,
            active: false,
            id: 5,
            name: 'Amparo Seguros (exemplo)',
            specialty: 'seguros_beneficios',
          },
        ],
      })
    ),
    http.post('http://localhost/partners', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      created.push(body);

      return HttpResponse.json({ data: { ...body, active: true, id: 9 } }, { status: 201 });
    }),
    http.patch('http://localhost/partners/3', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      patched.push(body);

      return HttpResponse.json({ data: { ...PARTNER_FIXTURE, ...body } });
    }),
    http.post('http://localhost/partners/3/access', () =>
      HttpResponse.json({ data: { ...PARTNER_ACCESS_FIXTURE, password: 'Kd83hfPq02LxZm4a' } }, { status: 201 })
    )
  );
});

describe('PartnersView', () => {
  it('lists the partners with specialty, contact and status', async () => {
    renderPartners();

    const rows = await screen.findAllByTestId('partner-row');

    expect(rows).toHaveLength(2);
    expect(within(rows[0]!).getByText('NexoERP (exemplo)')).toBeTruthy();
    expect(within(rows[0]!).getByText('ERP/e-invoicing')).toBeTruthy();
    expect(within(rows[0]!).getByText('contato3@parceiro.exemplo')).toBeTruthy();
    expect(within(rows[1]!).getByTestId('partner-status').textContent).toContain('Inactive');
  });

  it('creates a partner from the dialog and shows it in the list', async () => {
    renderPartners();

    await screen.findAllByTestId('partner-row');
    await fireEvent.click(screen.getByTestId('add-partner'));
    await fireEvent.update(screen.getByTestId('partner-name'), 'Margem Certa (exemplo)');
    await fireEvent.update(screen.getByTestId('partner-specialty'), 'precificacao_controladoria');
    await fireEvent.update(screen.getByTestId('partner-email'), 'contato7@parceiro.exemplo');
    await fireEvent.submit(screen.getByTestId('partner-form'));

    await waitFor(() => expect(created).toHaveLength(1));
    expect(created[0]).toMatchObject({
      email: 'contato7@parceiro.exemplo',
      name: 'Margem Certa (exemplo)',
      specialty: 'precificacao_controladoria',
    });
    await waitFor(() => expect(screen.getAllByTestId('partner-row')).toHaveLength(3));
  });

  it('refuses the form without a name, a specialty or an e-mail, before asking the API', async () => {
    renderPartners();

    await screen.findAllByTestId('partner-row');
    await fireEvent.click(screen.getByTestId('add-partner'));
    await fireEvent.submit(screen.getByTestId('partner-form'));

    expect(created).toHaveLength(0);
    expect(screen.getAllByText(/required/i).length).toBeGreaterThan(0);
  });

  it('deactivates a partner and the row says so', async () => {
    renderPartners();

    const rows = await screen.findAllByTestId('partner-row');

    await fireEvent.click(within(rows[0]!).getByTestId('partner-toggle'));

    await waitFor(() => expect(patched).toEqual([{ active: false }]));
    await waitFor(() =>
      expect(within(screen.getAllByTestId('partner-row')[0]!).getByTestId('partner-status').textContent).toContain(
        'Inactive'
      )
    );
  });

  // rnc-2 F1, R2: "Acesso ao portal" on an active partner's row; the row then says the portal is on.
  it('gives an active partner its portal login from the row, and the row says so', async () => {
    renderPartners();

    const rows = await screen.findAllByTestId('partner-row');

    expect(within(rows[1]!).queryByTestId('partner-access')).toBeNull();

    await fireEvent.click(within(rows[0]!).getByTestId('partner-access'));
    await fireEvent.update(screen.getByTestId('access-username'), 'parceiro.3');
    await fireEvent.submit(screen.getByTestId('access-form'));

    expect((await screen.findByTestId('access-password')).textContent).toContain('Kd83hfPq02LxZm4a');
    await waitFor(() =>
      expect(within(screen.getAllByTestId('partner-row')[0]!).getByTestId('partner-portal')).toBeTruthy()
    );
    expect(screen.getAllByTestId('partner-row')[0]!.textContent).not.toContain('Kd83hfPq02LxZm4a');
  });
});
