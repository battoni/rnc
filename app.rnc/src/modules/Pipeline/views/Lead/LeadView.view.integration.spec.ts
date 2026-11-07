import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Skeleton from 'primevue/skeleton';
import Textarea from 'primevue/textarea';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import MLeadDiagnosis from '../../components/molecules/MLeadDiagnosis/MLeadDiagnosis.vue';
import MLeadNoteForm from '../../components/molecules/MLeadNoteForm/MLeadNoteForm.vue';
import MLeadTimeline from '../../components/molecules/MLeadTimeline/MLeadTimeline.vue';
import MLostReasonForm from '../../components/molecules/MLostReasonForm/MLostReasonForm.vue';
import { LEAD_DETAIL_FIXTURE } from '../../test/fixtures';
import LeadView from './LeadView.view.vue';

/*
 * The lead detail (rnc-1 F5, R19): the full diagnostic with its details, the consent as given, the
 * contact as real links, the timeline with who, and a note that lands on it.
 */
const routeParams: { id: string } = { id: '7' };
const notes: Record<string, unknown>[] = [];

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: routeParams, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  AEmptyState: { props: ['title'], template: '<div data-testid="empty-state">{{ title }}</div>' },
  AFormError: { props: ['error'], template: '<p v-if="error">{{ error }}</p>' },
  MMainDialog: { props: ['visible'], template: '<div v-if="visible" data-testid="dialog"><slot /></div>' },
  RouterLink: { props: ['to'], template: '<a><slot :navigate="() => {}" /></a>' },
};

function renderLead() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 3, last_name: 'Gomes', name: 'Patrícia', role: 'Equipe', role_slug: 'equipe', username: 'patricia' },
    ['leads.read', 'leads.update']
  );

  return renderWithPlugins(LeadView, {
    global: {
      components: { Button, MLeadDiagnosis, MLeadNoteForm, MLeadTimeline, MLostReasonForm, Select, Skeleton, Textarea },
      stubs: STUBS,
    },
    pinia,
  });
}

beforeEach(() => {
  routeParams.id = '7';
  notes.length = 0;
  server.use(
    http.get('http://localhost/leads/7', () => HttpResponse.json({ data: structuredClone(LEAD_DETAIL_FIXTURE) })),
    http.get('http://localhost/leads/404', () =>
      HttpResponse.json({ data: null, error: 'pipeline.notFound' }, { status: 404 })
    ),
    http.post('http://localhost/leads/7/notes', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      notes.push(body);

      return HttpResponse.json(
        {
          data: {
            at: '2026-11-19T10:05:00-03:00',
            data: { text: body.text },
            id: 50,
            type: 'nota.adicionada',
            user: { id: 3, name: 'Patrícia Gomes' },
          },
        },
        { status: 201 }
      );
    })
  );
});

describe('LeadView', () => {
  it('shows the full diagnostic with the detail the visitor does not see', async () => {
    renderLead();

    await screen.findByTestId('lead-detail');

    expect(screen.getByTestId('lead-company').textContent).toContain('Padaria Trigo Dourado');
    expect(screen.getByTestId('diagnosis-detail').textContent).toContain('No Simples, a venda para empresa');
    expect(screen.getByTestId('diagnosis-item').textContent).toContain('LC 214/2025');
  });

  it('shows the consent as given, with its text and when', async () => {
    renderLead();

    const consent = await screen.findByTestId('lead-consent');

    expect(consent.dataset.given).toBe('true');
    expect(consent.textContent).toContain('Agreed to be introduced to partners');
    expect(consent.textContent).toContain('Autorizo a Franco');
    expect(consent.textContent).toContain('version 1');
  });

  it('offers the contact as real tel, WhatsApp and mailto links', async () => {
    renderLead();

    await screen.findByTestId('lead-detail');

    expect(screen.getByTestId('contact-call').getAttribute('href')).toBe('tel:+5511900000001');
    expect(screen.getByTestId('contact-whatsapp').getAttribute('href')).toBe('https://wa.me/5511900000001');
    expect(screen.getByTestId('contact-email').getAttribute('href')).toBe(
      'mailto:ana@padaria-trigo-dourado.exemplo.com.br'
    );
  });

  it('lists the timeline with who did each line', async () => {
    renderLead();

    const items = await screen.findAllByTestId('timeline-item');

    expect(items[0]!.textContent).toContain('Partial lead → Lead (dragged)');
    expect(within(items[0]!).getByTestId('timeline-who').textContent).toContain('by Patrícia Gomes');
    expect(within(items[1]!).getByTestId('timeline-who').textContent).toContain('visitor');
  });

  it('adds a note to the notes and the timeline', async () => {
    renderLead();

    await screen.findByTestId('lead-detail');
    await fireEvent.update(screen.getByTestId('note-input'), 'Ligar na segunda');
    await fireEvent.submit(screen.getByTestId('note-form'));

    await waitFor(() => expect(notes).toEqual([{ text: 'Ligar na segunda' }]));
    expect((await screen.findByTestId('note')).textContent).toContain('Ligar na segunda');
    expect(screen.getAllByTestId('timeline-item')[0]!.textContent).toContain('Note: Ligar na segunda');
  });

  it('says a lead that is not there does not exist', async () => {
    routeParams.id = '404';

    renderLead();

    expect((await screen.findByTestId('empty-state')).textContent).toContain('Lead not found');
  });
});
