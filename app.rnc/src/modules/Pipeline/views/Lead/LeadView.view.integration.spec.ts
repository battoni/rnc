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
import MLeadChannelChip from '../../components/molecules/MLeadChannelChip/MLeadChannelChip.vue';
import MLeadDiagnosis from '../../components/molecules/MLeadDiagnosis/MLeadDiagnosis.vue';
import MLeadNoteForm from '../../components/molecules/MLeadNoteForm/MLeadNoteForm.vue';
import MLeadTimeline from '../../components/molecules/MLeadTimeline/MLeadTimeline.vue';
import MLostReasonForm from '../../components/molecules/MLostReasonForm/MLostReasonForm.vue';
import { LEAD_CHANNEL } from '../../enums';
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
  MLeadRouting: {
    props: ['canRefer', 'leadId'],
    template: '<section data-testid="lead-routing" :data-lead="leadId" :data-can-refer="String(canRefer)" />',
  },
  MMainDialog: { props: ['visible'], template: '<div v-if="visible" data-testid="dialog"><slot /></div>' },
  RouterLink: { props: ['to'], template: '<a><slot :navigate="() => {}" /></a>' },
};

function renderLead(modules: string[] = ['partners']) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    {
      firm: { modules, name: 'Franco Contabilidade', slug: 'franco' },
      id: 3,
      last_name: 'Gomes',
      name: 'Patrícia',
      role: 'Equipe',
      role_slug: 'equipe',
      username: 'patricia',
    } as never,
    ['leads.read', 'leads.update']
  );

  return renderWithPlugins(LeadView, {
    global: {
      components: {
        Button,
        MLeadChannelChip,
        MLeadDiagnosis,
        MLeadNoteForm,
        MLeadTimeline,
        MLostReasonForm,
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

  it("wears the sub-brand's chip beside the company (rnc-3 A4)", async () => {
    server.use(
      http.get('http://localhost/leads/7', () =>
        HttpResponse.json({
          data: { ...structuredClone(LEAD_DETAIL_FIXTURE), brand: { id: 1, short_name: 'Franco Express' } },
        })
      )
    );
    renderLead();

    expect((await screen.findByTestId('lead-brand')).textContent).toContain('Franco Express');
  });

  it('wears no brand chip for a main-brand lead', async () => {
    renderLead();

    await screen.findByTestId('lead-detail');

    expect(screen.queryByTestId('lead-brand')).toBeNull();
  });

  it('names the channel the lead came in by, the form included (rnc-2 R14)', async () => {
    server.use(
      http.get('http://localhost/leads/7', () =>
        HttpResponse.json({ data: { ...structuredClone(LEAD_DETAIL_FIXTURE), channel: LEAD_CHANNEL.CHAT } })
      )
    );
    renderLead();

    const chip = await screen.findByTestId('lead-channel');

    expect(chip.dataset.channel).toBe('chat');
    expect(chip.textContent?.trim()).toBe('Chat');
  });

  it("shows a form lead's channel too", async () => {
    renderLead();

    expect((await screen.findByTestId('lead-channel')).textContent?.trim()).toBe('Form');
  });

  it('shows the consent as given, with its text and when', async () => {
    renderLead();

    const consent = await screen.findByTestId('lead-consent');

    expect(consent.dataset.given).toBe('true');
    expect(consent.textContent).toContain('Agreed to be introduced to partners');
    expect(consent.textContent).toContain('Autorizo a Franco');
    expect(consent.textContent).toContain('version 1');
  });

  describe('a beta signup (rnc-4 R29, Chrome QA 1.10)', () => {
    function serveBetaLead() {
      server.use(
        http.get('http://localhost/leads/7', () =>
          HttpResponse.json({
            data: {
              ...structuredClone(LEAD_DETAIL_FIXTURE),
              channel: LEAD_CHANNEL.BETA,
              cnpj: null,
              consent: {
                given: true,
                given_at: '2026-11-18T09:12:00-03:00',
                text: 'Autorizo a plataforma a me contatar sobre o programa beta.',
                version: 2,
                via: 'formulario',
              },
              path: 'beta',
              profile: {
                b2b: null,
                band: null,
                city: 'Curitiba',
                clients_band: 'de_101_a_300',
                exporta: false,
                incentivos: false,
                message: 'Queremos começar em janeiro.',
                regime: null,
                role: 'Sócia',
                sector: null,
                uf: 'PR',
              },
            },
          })
        )
      );
    }

    it('shows the fields the beta form collected, not the empty diagnostic', async () => {
      serveBetaLead();
      renderLead();

      await screen.findByTestId('lead-consent');
      const profile = screen.getByTestId('lead-profile');

      expect(profile.textContent).toContain('Sócia');
      expect(profile.textContent).toContain('Curitiba/PR');
      expect(profile.textContent).toContain('101 to 300');
      expect(profile.textContent).toContain('Queremos começar em janeiro.');
      expect(profile.textContent).toContain('by beta signup');
      expect(profile.textContent).not.toContain('pipeline.lead');
      expect(profile.textContent).not.toContain('ICMS incentives');
    });

    it('sums the consent up as contact about the beta, keeping its text and version', async () => {
      serveBetaLead();
      renderLead();

      const consent = await screen.findByTestId('lead-consent');

      expect(consent.textContent).toContain('Agreed to be contacted about the beta');
      expect(consent.textContent).not.toContain('partners');
      expect(consent.textContent).toContain('contatar sobre o programa beta');
      expect(consent.textContent).toContain('version 2');
    });
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

  it('puts the routing panel under the diagnostic, with the referral allowed by leads.update (F6)', async () => {
    renderLead();

    const panel = await screen.findByTestId('lead-routing');

    expect(panel.getAttribute('data-lead')).toBe('7');
    expect(panel.getAttribute('data-can-refer')).toBe('true');
  });

  it('writes a referral line on the timeline with the partner and the score (F6)', async () => {
    server.use(
      http.get('http://localhost/leads/7', () => {
        const detail = structuredClone(LEAD_DETAIL_FIXTURE);

        detail.timeline = [
          {
            at: '2026-11-19T11:00:00-03:00',
            data: { partner: 'Margem Certa (exemplo)', score: 92 },
            id: 99,
            type: 'encaminhamento.enviado',
            user: { id: 3, name: 'Patrícia Gomes' },
          },
          ...detail.timeline,
        ];

        return HttpResponse.json({ data: detail });
      })
    );
    renderLead();

    const items = await screen.findAllByTestId('timeline-item');

    expect(items[0]!.textContent).toContain('Referred to Margem Certa (exemplo) (92%)');
    expect(items[0]!.textContent).toContain('Patrícia Gomes');
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
  /*
   * rnc-4 W38: the lead's partner matches and referral are the Partner module's
   * (`leads/{id}/partner-matches`, 404 without `partners`), so a firm without it gets no routing
   * panel — and asks for no matches.
   */
  it('rnc-4: draws no partner routing when the firm has `partners` off', async () => {
    renderLead([]);

    await screen.findByTestId('lead-detail');

    expect(screen.queryByTestId('lead-routing')).toBeNull();
  });

  it('rnc-4: draws the partner routing while `partners` is on', async () => {
    renderLead();

    expect(await screen.findByTestId('lead-routing')).toBeTruthy();
  });
});
