import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import MBookingConfirmation from '../../components/molecules/MBookingConfirmation/MBookingConfirmation.vue';
import MChatReceptionist from '../../components/molecules/MChatReceptionist/MChatReceptionist.vue';
import MChatResult from '../../components/molecules/MChatResult/MChatResult.vue';
import MChoiceGroup from '../../components/molecules/MChoiceGroup/MChoiceGroup.vue';
import MCnpjLookup from '../../components/molecules/MCnpjLookup/MCnpjLookup.vue';
import MDiagnosticContactForm from '../../components/molecules/MDiagnosticContactForm/MDiagnosticContactForm.vue';
import MDiagnosticProgress from '../../components/molecules/MDiagnosticProgress/MDiagnosticProgress.vue';
import MDiagnosticResult from '../../components/molecules/MDiagnosticResult/MDiagnosticResult.vue';
import MImpactSimulator from '../../components/molecules/MImpactSimulator/MImpactSimulator.vue';
import MSlotPicker from '../../components/molecules/MSlotPicker/MSlotPicker.vue';
import {
  AVAILABILITY_FIXTURE,
  BOOKING_FIXTURE,
  COMPANY_FIXTURE,
  CONSENT_TEXT,
  LEAD_FIXTURE,
  RESULT_FIXTURE,
  SIMULATION_FIXTURE,
  TOKEN_FIXTURE,
} from '../../test/fixtures';
import DiagnosticView from './DiagnosticView.view.vue';

/*
 * The diagnostic's step flow (rnc-1 F3, R9–R14): step 1 by the quiz or the CNPJ, the contact right
 * after it, two more steps, then the locked result with the simulator and the CTA F4 will wire.
 * The API is msw; every request body is kept so the spec reads what each step saved.
 */
const routeQuery: { setor?: string } = {};
const sendBeacon = vi.fn<(url: string, body: Blob) => boolean>(() => true);
const requests: { body: any; method: string; path: string }[] = [];

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: routeQuery }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' },
  ThePublicLayout: { template: '<div data-testid="public-layout"><slot /></div>' },
};

const API = 'http://localhost/public/diagnostics';

function record(method: string, path: string, body: unknown) {
  requests.push({ body, method, path });
}

function patches() {
  return requests.filter((request) => request.method === 'PATCH').map((request) => request.body);
}

async function beacons() {
  return Promise.all(sendBeacon.mock.calls.map(async ([, body]) => JSON.parse(await body.text())));
}

function renderDiagnostic() {
  return renderWithPlugins(DiagnosticView, {
    global: {
      components: {
        Button,
        Checkbox,
        InputText,
        MBookingConfirmation,
        MChatReceptionist,
        MChatResult,
        MChoiceGroup,
        MCnpjLookup,
        MDiagnosticContactForm,
        MDiagnosticProgress,
        MDiagnosticResult,
        MImpactSimulator,
        MSlotPicker,
      },
      stubs: STUBS,
    },
  });
}

async function answerQuizStepOne() {
  await fireEvent.click(screen.getByTestId('path-quiz'));
  await waitFor(() => expect(requests.some((request) => request.method === 'POST')).toBe(true));
  await fireEvent.click(screen.getByTestId('sector-servicos'));
  await fireEvent.click(screen.getByTestId('regime-simples'));
  await fireEvent.click(screen.getByTestId('step-next'));
  await screen.findByTestId('contact-form');
}

async function giveContact() {
  await fireEvent.update(screen.getByTestId('contact-company'), 'Clínica Horizonte');
  await fireEvent.update(screen.getByTestId('contact-email'), 'ana@horizonte.com.br');
  await fireEvent.click(screen.getByTestId('contact-save'));
  await screen.findByTestId('step-profile');
}

beforeEach(() => {
  delete routeQuery.setor;
  requests.length = 0;
  sendBeacon.mockClear();
  localStorage.clear();
  sessionStorage.clear();
  vi.stubGlobal('navigator', { ...navigator, sendBeacon });
  server.use(
    http.get('http://localhost/public/firm', () => HttpResponse.json({ data: { name: 'Franco Contabilidade' } })),
    http.post(API, async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;
      record('POST', 'start', body);

      return HttpResponse.json(
        {
          data: { ...LEAD_FIXTURE, path: body.path, profile: { ...LEAD_FIXTURE.profile, sector: body.sector ?? null } },
        },
        { status: 201 }
      );
    }),
    http.patch(`${API}/:token`, async ({ request }) => {
      const body = await request.json();
      record('PATCH', 'update', body);

      return HttpResponse.json({ data: { ...LEAD_FIXTURE, token: undefined } });
    }),
    http.get(`${API}/:token/result`, () => HttpResponse.json({ data: RESULT_FIXTURE })),
    http.get(`${API}/:token/availability`, () => HttpResponse.json({ data: AVAILABILITY_FIXTURE })),
    http.post(`${API}/:token/booking`, async ({ request }) => {
      record('POST', 'booking', await request.json());

      return HttpResponse.json({ data: BOOKING_FIXTURE }, { status: 201 });
    }),
    http.get(`${API}/:token/simulation`, () => HttpResponse.json({ data: SIMULATION_FIXTURE }))
  );
});

afterEach(() => vi.unstubAllGlobals());

describe('DiagnosticView — the quiz path', () => {
  it('starts the lead when the visitor picks a path, with the visit it came from', async () => {
    renderDiagnostic();

    await fireEvent.click(screen.getByTestId('path-quiz'));

    await waitFor(() => expect(requests[0]?.body).toMatchObject({ path: 'quiz', sector: null, source: 'direto' }));
    expect(requests[0]!.body.visitor).toBe(localStorage.getItem('rnc:visitor'));
    await waitFor(async () =>
      expect((await beacons()).map((event) => [event.type, event.lead_token])).toContainEqual([
        'diag.start',
        TOKEN_FIXTURE,
      ])
    );
  });

  it('asks for the sector and the regime before going on', async () => {
    renderDiagnostic();

    await fireEvent.click(screen.getByTestId('path-quiz'));
    await waitFor(() => expect(requests).toHaveLength(1));
    await fireEvent.click(screen.getByTestId('step-next'));

    expect(screen.getByTestId('step-error')).toBeTruthy();
    expect(patches()).toHaveLength(0);
  });

  it('asks the contact right after step 1, with the consent box unticked, and saves it as the last step', async () => {
    renderDiagnostic();
    await answerQuizStepOne();

    expect(patches()[0]).toEqual({ profile: { regime: 'simples', sector: 'servicos' }, step: 'contato' });
    expect(screen.getByTestId('contact-form').textContent).toContain(CONSENT_TEXT);
    expect((screen.getByTestId('contact-consent').querySelector('input') as HTMLInputElement).checked).toBe(false);

    await giveContact();

    expect(patches()[1]).toEqual({
      contact: { company: 'Clínica Horizonte', consent: false, email: 'ana@horizonte.com.br', name: null, phone: null },
      step: 'perfil',
    });
    expect((await beacons()).map((event) => [event.type, event.lead_token])).toContainEqual([
      'diag.contact',
      TOKEN_FIXTURE,
    ]);
  });

  it('refuses a contact without the company or a way to reach the person', async () => {
    renderDiagnostic();
    await answerQuizStepOne();

    await fireEvent.update(screen.getByTestId('contact-company'), 'Clínica Horizonte');
    await fireEvent.click(screen.getByTestId('contact-save'));

    expect(screen.getByTestId('contact-error')).toBeTruthy();
    expect(patches()).toHaveLength(1);
  });

  it('walks the two quiz steps to the locked result, the simulator and the specialist CTA', async () => {
    renderDiagnostic();
    await answerQuizStepOne();
    await giveContact();

    await fireEvent.click(screen.getByTestId('b2b-80'));
    await fireEvent.click(screen.getByTestId('step-next'));
    await screen.findByTestId('step-revenue');

    expect(patches()[2]).toEqual({ profile: { b2b: 80, regime: 'simples', sector: 'servicos' }, step: 'faturamento' });

    await fireEvent.click(screen.getByTestId('band-b2'));
    await fireEvent.click(screen.getByTestId('step-next'));

    const result = await screen.findByTestId('result');

    expect(patches()[3]).toEqual({ profile: { band: 'b2', exporta: false, incentivos: false }, step: 'resultado' });
    expect(screen.getByTestId('result-summary').textContent).toMatch(/3 opportunities and 2 risks/);
    expect(screen.getAllByTestId('result-item')).toHaveLength(5);
    expect(screen.getAllByTestId('result-item')[0]!.textContent).toContain('R$ 18–54 mil/ano');
    expect(screen.getAllByTestId('locked')).toHaveLength(5);
    expect(result.textContent).not.toContain('Clientes PJ tomam crédito integral');
    expect(screen.getAllByTestId('sim-year')).toHaveLength(8);
    expect((await beacons()).map((event) => event.type)).toContain('diag.result');

    await fireEvent.click(screen.getByTestId('schedule-cta'));

    await screen.findAllByTestId('specialist');

    // The contact gave an e-mail, so the picker does not ask for one (AM5).
    expect(screen.queryByTestId('booking-email')).toBeNull();

    await fireEvent.click(screen.getAllByTestId('slot')[1]!);
    await fireEvent.click(screen.getByTestId('slot-confirm'));

    const confirmed = await screen.findByTestId('booking-confirmed');

    expect(requests.find((request) => request.path === 'booking')?.body).toEqual({
      specialist_id: 3,
      starts_at: '2026-11-24T14:30:00-03:00',
    });
    expect(confirmed.textContent).toContain('Juliana Reis');
    expect(confirmed.textContent).toContain('14:30');
    expect(JSON.parse(screen.getByTestId('open-report').getAttribute('data-to')!)).toEqual({
      name: 'site-report',
      params: { token: BOOKING_FIXTURE.report_token },
    });
    expect((await beacons()).map((event) => event.type)).toContain('booking');
  });

  it('starts with the sector a sector page sent', async () => {
    routeQuery.setor = 'saude';
    renderDiagnostic();

    await fireEvent.click(screen.getByTestId('path-quiz'));

    await waitFor(() => expect(requests[0]?.body.sector).toBe('saude'));
    expect(screen.getByTestId('sector-saude').getAttribute('aria-checked')).toBe('true');
  });
});

describe('DiagnosticView — the CNPJ path', () => {
  async function lookUp(cnpj: string) {
    await fireEvent.click(screen.getByTestId('path-cnpj'));
    await waitFor(() => expect(requests).toHaveLength(1));
    await fireEvent.update(screen.getByTestId('cnpj-input'), cnpj);
    await fireEvent.click(screen.getByTestId('cnpj-lookup'));
  }

  it('shows the Receita · Simples · CNAE sequence, then the company it found', async () => {
    server.use(
      http.post(`${API}/:token/cnpj`, () =>
        HttpResponse.json({
          data: {
            company: COMPANY_FIXTURE,
            lead: {
              ...LEAD_FIXTURE,
              company: COMPANY_FIXTURE.name,
              profile: { ...LEAD_FIXTURE.profile, band: 'b2', regime: 'simples', sector: 'comercio' },
            },
            lookup: 'found',
          },
        })
      )
    );
    renderDiagnostic();

    await lookUp('11.222.333/0001-81');

    expect(screen.getAllByTestId('lookup-step')).toHaveLength(3);

    const card = await screen.findByTestId('company-card', {}, { timeout: 3000 });

    expect(card.textContent).toContain('MERCADO BOA VISTA LTDA');

    await fireEvent.click(screen.getByTestId('step-next'));

    await screen.findByTestId('contact-form');
    expect(patches()[0]).toEqual({ profile: { regime: 'simples', sector: 'comercio' }, step: 'contato' });
  });

  it('falls back to the quiz with a friendly message when the CNPJ is not found', async () => {
    server.use(
      http.post(`${API}/:token/cnpj`, () =>
        HttpResponse.json({ data: { company: null, lead: LEAD_FIXTURE, lookup: 'unavailable' } })
      )
    );
    renderDiagnostic();

    await lookUp('11222333000181');

    await screen.findByTestId('cnpj-fallback', {}, { timeout: 3000 });
    await fireEvent.click(screen.getByTestId('cnpj-fallback-quiz'));

    expect(screen.getByTestId('sector-comercio')).toBeTruthy();
  });

  it('says the CNPJ is wrong when the API refuses its check digits', async () => {
    server.use(
      http.post(`${API}/:token/cnpj`, () =>
        HttpResponse.json({ errors: { cnpj: ['diagnostic.cnpj.invalid'] }, message: 'invalid' }, { status: 422 })
      )
    );
    renderDiagnostic();

    await lookUp('11222333000182');

    expect(await screen.findByTestId('cnpj-invalid', {}, { timeout: 3000 })).toBeTruthy();
  });

  it('says the diagnostic is unavailable, not that the CNPJ is wrong, when the start failed — and retries it', async () => {
    server.use(http.post(API, () => HttpResponse.json({ message: 'down' }, { status: 500 }), { once: true }));
    renderDiagnostic();

    await fireEvent.click(screen.getByTestId('path-cnpj'));
    await screen.findByTestId('step-error');
    await fireEvent.update(screen.getByTestId('cnpj-input'), '11.222.333/0001-81');
    await fireEvent.click(screen.getByTestId('cnpj-lookup'));

    expect(screen.queryByTestId('cnpj-invalid')).toBeNull();
    expect(screen.getByTestId('step-error').textContent).toContain('Could not save right now');
    await waitFor(() => expect(requests.filter((request) => request.path === 'start')).toHaveLength(1));
  });

  it('does not call the API for a CNPJ without 14 digits', async () => {
    renderDiagnostic();

    await lookUp('1122');

    expect(screen.getByTestId('cnpj-invalid')).toBeTruthy();
    expect(requests).toHaveLength(1);
  });
});

describe('DiagnosticView — Formulário · Conversar no chat (rnc-2 R12, R14)', () => {
  function mode(key: 'chat' | 'form') {
    return screen.getByTestId(`mode-${key}`);
  }

  async function tap(value: string) {
    const reply = await waitFor(() => {
      const found = screen.getAllByTestId('chat-reply').find((button) => button.getAttribute('data-value') === value);

      expect(found, `reply ${value}`).toBeDefined();

      return found!;
    });

    await fireEvent.click(reply);
  }

  it('opens on the form, with the chat one tap away', async () => {
    renderDiagnostic();

    expect(mode('form').getAttribute('aria-pressed')).toBe('true');
    expect(mode('chat').getAttribute('aria-pressed')).toBe('false');
    expect(screen.queryByTestId('chat')).toBeNull();

    await fireEvent.click(mode('chat'));

    expect(await screen.findByTestId('chat')).toBeTruthy();
    expect(mode('chat').getAttribute('aria-pressed')).toBe('true');
    expect(screen.queryByTestId('path-quiz')).toBeNull();
  });

  it('starts a chat lead from the chat and a form lead from the form (A10)', async () => {
    renderDiagnostic();

    await fireEvent.click(mode('chat'));
    await tap('quiz');
    await waitFor(() => expect(requests.some((request) => request.method === 'POST')).toBe(true));

    expect(requests[0]!.body).toMatchObject({ channel: 'chat', path: 'quiz' });
  });

  it('keeps every answer when the visitor switches to the form mid-way, and back (A10)', async () => {
    renderDiagnostic();

    await fireEvent.click(mode('chat'));
    await tap('quiz');
    await waitFor(() => expect(requests.some((request) => request.method === 'POST')).toBe(true));
    await tap('servicos');
    await tap('simples');
    await waitFor(() => expect(screen.getByTestId('chat').getAttribute('data-turn')).toBe('company'));
    await fireEvent.update(screen.getByTestId('chat-input'), 'Clínica Horizonte');
    await fireEvent.submit(screen.getByTestId('chat-compose'));

    await fireEvent.click(mode('form'));

    const company = (await screen.findByTestId('contact-company')) as HTMLInputElement;

    expect(company.value).toBe('Clínica Horizonte');
    expect(patches()[0]).toEqual({ profile: { regime: 'simples', sector: 'servicos' }, step: 'contato' });

    await fireEvent.update(screen.getByTestId('contact-email'), 'ana@horizonte.com.br');
    await fireEvent.click(screen.getByTestId('contact-save'));
    await screen.findByTestId('step-profile');

    expect(screen.getByTestId('sector-servicos').getAttribute('aria-checked')).toBe('true');
    expect(screen.getByTestId('regime-simples').getAttribute('aria-checked')).toBe('true');

    await fireEvent.click(mode('chat'));

    const chat = await screen.findByTestId('chat');

    expect(chat.getAttribute('data-turn')).toBe('b2b');
    expect(chat.textContent).toContain('I see you moved on in the form');
    expect(requests.filter((request) => request.method === 'POST')).toHaveLength(1);
  });

  it("opens the simulator in the form from the chat's result", async () => {
    renderDiagnostic();

    await fireEvent.click(mode('chat'));
    await tap('quiz');
    await waitFor(() => expect(requests.some((request) => request.method === 'POST')).toBe(true));
    await tap('servicos');
    await tap('simples');
    await waitFor(() => expect(screen.getByTestId('chat').getAttribute('data-turn')).toBe('company'));

    for (const text of ['Clínica Horizonte', 'Ana Souza', 'ana@horizonte.com.br']) {
      await fireEvent.update(screen.getByTestId('chat-input'), text);
      await fireEvent.submit(screen.getByTestId('chat-compose'));
    }

    await tap('autorizo');
    await tap('80');
    await tap('b2');
    await tap('nao');
    await tap('nao');
    await fireEvent.click(await screen.findByTestId('chat-simulator'));

    expect(await screen.findByTestId('result')).toBeTruthy();
    expect(screen.getAllByTestId('sim-year')).toHaveLength(8);
    expect(mode('form').getAttribute('aria-pressed')).toBe('true');
  });
});
