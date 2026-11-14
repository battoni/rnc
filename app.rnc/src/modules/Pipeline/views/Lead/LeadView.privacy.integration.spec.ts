import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Skeleton from 'primevue/skeleton';
import Textarea from 'primevue/textarea';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import MLeadChannelChip from '../../components/molecules/MLeadChannelChip/MLeadChannelChip.vue';
import MLeadDiagnosis from '../../components/molecules/MLeadDiagnosis/MLeadDiagnosis.vue';
import MLeadNoteForm from '../../components/molecules/MLeadNoteForm/MLeadNoteForm.vue';
import MLeadTimeline from '../../components/molecules/MLeadTimeline/MLeadTimeline.vue';
import { LEAD_DETAIL_FIXTURE } from '../../test/fixtures';
import LeadView from './LeadView.view.vue';

/*
 * rnc-4 R39 (F9b): the lead's own data on its detail, for whoever holds `leads.update` AND
 * `firm.settings` (W101) — "Export data" downloads `GET leads/{id}/export` as a JSON file, and
 * "Anonymise" asks first, saying it cannot be undone, then shows the detail the API answers.
 */
const confirmations: { message: string }[] = [];
const anonymiseCalls: string[] = [];
const clickedDownloads: { download: string; href: string }[] = [];
const savedBlobs: Blob[] = [];

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: { id: '7' }, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

// The confirm popup's own behaviour is PrimeVue's; here "yes" is answered at once and the words kept.
vi.mock('primevue/useconfirm', () => ({
  useConfirm: () => ({
    require: (options: { accept: () => void; message: string }) => {
      confirmations.push({ message: options.message });
      options.accept();
    },
  }),
}));

const STUBS = {
  AEmptyState: { props: ['title'], template: '<div data-testid="empty-state">{{ title }}</div>' },
  ConfirmPopup: { template: '<div />' },
  MLeadRouting: { template: '<section />' },
  MMainDialog: { props: ['visible'], template: '<div v-if="visible"><slot /></div>' },
  RouterLink: { props: ['to'], template: '<a><slot :navigate="() => {}" /></a>' },
};

const MANAGER_KEYS = ['leads.read', 'leads.update', 'firm.settings'];

const ANONYMISED = {
  ...LEAD_DETAIL_FIXTURE,
  anonymised_at: '2026-11-14T15:00:00-03:00',
  cnpj: null,
  company: null,
  contact_name: null,
  email: null,
  phone: null,
  timeline: [
    {
      at: '2026-11-14T15:00:00-03:00',
      data: { via: 'pedido' },
      id: 9,
      type: 'dados.anonimizados',
      user: { id: 4, name: 'Helena Prado' },
    },
    ...LEAD_DETAIL_FIXTURE.timeline,
  ],
};

function renderLead(keys: string[] = MANAGER_KEYS) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    {
      firm: { modules: [], name: 'Alvorada Contábil', slug: 'alvorada' },
      id: 4,
      last_name: 'Prado',
      name: 'Helena',
      role: 'Gestão',
      role_slug: 'gestao',
      username: 'helena.alvorada',
    } as never,
    keys
  );

  return renderWithPlugins(LeadView, {
    global: {
      components: {
        Button,
        MLeadChannelChip,
        MLeadDiagnosis,
        MLeadNoteForm,
        MLeadTimeline,
        Select,
        Skeleton,
        Textarea,
      },
      stubs: STUBS,
    },
    pinia,
  });
}

beforeEach(() => {
  confirmations.length = 0;
  anonymiseCalls.length = 0;
  clickedDownloads.length = 0;
  savedBlobs.length = 0;

  // jsdom has no object URLs; the class itself stays, since the API client builds `new URL(...)`.
  Object.assign(URL, {
    createObjectURL: (blob: Blob) => {
      savedBlobs.push(blob);

      return 'blob:lead-7';
    },
    revokeObjectURL: vi.fn(),
  });
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
    clickedDownloads.push({ download: this.download, href: this.getAttribute('href') ?? '' });
  });

  server.use(
    http.get('http://localhost/leads/7', () =>
      HttpResponse.json({ data: { ...structuredClone(LEAD_DETAIL_FIXTURE), anonymised_at: null } })
    ),
    http.get('http://localhost/leads/7/export', () =>
      HttpResponse.json({ data: { consents: [], lead: { company: 'Padaria Trigo Dourado', id: 7 }, timeline: [] } })
    ),
    http.post('http://localhost/leads/7/anonymise', ({ request }) => {
      anonymiseCalls.push(request.method);

      return HttpResponse.json({ data: ANONYMISED });
    })
  );
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('LeadView — the lead own data (rnc-4 R39)', () => {
  it('downloads the export as lead-<id>-dados.json holding the API answer', async () => {
    renderLead();

    await fireEvent.click(await screen.findByTestId('lead-export'));

    await waitFor(() => expect(clickedDownloads).toEqual([{ download: 'lead-7-dados.json', href: 'blob:lead-7' }]));
    expect(JSON.parse(await savedBlobs[0]!.text())).toEqual({
      consents: [],
      lead: { company: 'Padaria Trigo Dourado', id: 7 },
      timeline: [],
    });
  });

  it('anonymises only after a confirmation that says it cannot be undone, then shows the anonymised state', async () => {
    renderLead();

    await fireEvent.click(await screen.findByTestId('lead-anonymise'));

    expect(confirmations).toHaveLength(1);
    expect(confirmations[0]!.message).toContain('cannot be undone');

    await screen.findByTestId('lead-anonymised');

    expect(anonymiseCalls).toEqual(['POST']);
    expect(screen.queryByTestId('lead-anonymise')).toBeNull();
    expect(screen.getByTestId('lead-contact').textContent).not.toContain('Ana Almeida');
    expect(screen.getAllByTestId('timeline-item')[0]!.dataset.type).toBe('dados.anonimizados');
    expect(screen.getAllByTestId('timeline-item')[0]!.textContent).not.toContain('dados.anonimizados');
  });

  it('opens an already anonymised lead in its anonymised state, with no anonymise button', async () => {
    server.use(http.get('http://localhost/leads/7', () => HttpResponse.json({ data: ANONYMISED })));

    renderLead();

    expect(await screen.findByTestId('lead-anonymised')).toBeTruthy();
    expect(screen.queryByTestId('lead-anonymise')).toBeNull();
  });

  it('shows neither button to the team (`leads.update` without `firm.settings`)', async () => {
    renderLead(['leads.read', 'leads.update']);

    await screen.findByTestId('lead-detail');

    expect(screen.queryByTestId('lead-export')).toBeNull();
    expect(screen.queryByTestId('lead-anonymise')).toBeNull();
  });
});
