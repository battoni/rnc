import { defineComponent, ref } from 'vue';
import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { mount } from '@vue/test-utils';
import { http, HttpResponse } from 'msw';
import { createPinia } from 'pinia';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import PrimeVue from 'primevue/config';
import InputText from 'primevue/inputtext';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { UseChatReceptionist } from './useChatReceptionist';
import type { UseDiagnostic } from './useDiagnostic';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import i18n from '@Libraries/i18n';
import MChoiceGroup from '../components/molecules/MChoiceGroup/MChoiceGroup.vue';
import MDiagnosticContactForm from '../components/molecules/MDiagnosticContactForm/MDiagnosticContactForm.vue';
import MDiagnosticProgress from '../components/molecules/MDiagnosticProgress/MDiagnosticProgress.vue';
import MDiagnosticResult from '../components/molecules/MDiagnosticResult/MDiagnosticResult.vue';
import MImpactSimulator from '../components/molecules/MImpactSimulator/MImpactSimulator.vue';
import { CHAT_TURN, DIAGNOSTIC_PATH, DIAGNOSTIC_STEP, LOOKUP_STATUS } from '../enums';
import { COMPANY_FIXTURE, LEAD_FIXTURE, RESULT_FIXTURE, SIMULATION_FIXTURE } from '../test/fixtures';
import DiagnosticView from '../views/Diagnostic/DiagnosticView.view.vue';
import { useChatReceptionist } from './useChatReceptionist';
import { useDiagnostic } from './useDiagnostic';

/*
 * The chat receptionist over the form's own diagnostic (rnc-2 F5, R12–R14).
 *
 * A10's proof (plan AM8): for "Simples, B2B 80 %, serviços" the PATCH bodies the chat produces
 * deep-equal the form's, in order — the same profile, the same contact, the same steps. The form's
 * walk is the real `DiagnosticView`; the chat's is `useChatReceptionist` driven turn by turn, half
 * by quick replies and half by typed words. The API is msw and keeps every body.
 */
const API = 'http://localhost/public/diagnostics';
const requests: { body: any; method: string; path: string }[] = [];
const sendBeacon = vi.fn<(url: string, body: Blob) => boolean>(() => true);
const lookupAnswer = { company: COMPANY_FIXTURE as typeof COMPANY_FIXTURE | null, status: 200 };

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

function patches() {
  return requests.filter((request) => request.method === 'PATCH').map((request) => request.body);
}

function startBody() {
  return requests.find((request) => request.path === 'start')?.body;
}

/** The two composables in a host with nothing on screen, so the spec drives the chat itself. */
function mountChat(isActive = ref(true)) {
  let exposed: { chat: UseChatReceptionist; diagnostic: UseDiagnostic } | null = null;

  mount(
    defineComponent({
      setup() {
        const diagnostic = useDiagnostic();

        exposed = { chat: useChatReceptionist(diagnostic, isActive), diagnostic };

        return () => null;
      },
    }),
    { global: { plugins: [i18n, createPinia(), PrimeVue] } }
  );

  return exposed!;
}

/** Answers the turn the chat is on and waits until it has moved past it. */
async function reply(chat: UseChatReceptionist, value: string) {
  const asked = chat.turn.value;
  const option = chat.prompt.value?.replies.find((entry) => entry.value === value);

  await waitFor(() => expect(chat.isBusy.value).toBe(false));
  expect(option, `a quick reply "${value}" at ${asked}`).toBeDefined();
  chat.answer(option!);
  await waitFor(() => expect(chat.turn.value !== asked || chat.isDone.value).toBe(true));
}

async function type(chat: UseChatReceptionist, text: string) {
  await waitFor(() => expect(chat.isBusy.value).toBe(false));

  const asked = chat.turn.value;

  chat.typed.value = text;
  chat.send();
  await waitFor(() => expect(chat.turn.value !== asked || chat.isDone.value).toBe(true));
}

/** The start has answered: its token is kept and the `diag.start` beacon has gone. */
async function waitStarted() {
  await waitFor(async () => {
    const types = await Promise.all(
      sendBeacon.mock.calls.map(async ([, body]) => (JSON.parse(await body.text()) as { type: string }).type)
    );

    expect(types).toContain('diag.start');
  });
}

/** "Simples, B2B 80 %, serviços" through the chat, the way a visitor would mix taps and words. */
async function chatWalk(chat: UseChatReceptionist) {
  await type(chat, 'não tenho');
  await waitStarted();
  await type(chat, 'serviços');
  await reply(chat, 'simples');
  await type(chat, 'Clínica Horizonte');
  await type(chat, 'Ana Souza');
  await type(chat, '(11) 98888-7777');
  await reply(chat, 'autorizo');
  await type(chat, '80%');
  await reply(chat, 'b2');
  await type(chat, 'não');
  await reply(chat, 'nao');
  await waitFor(() => expect(chat.isDone.value).toBe(true));
}

/** The same profile through the form's screens. */
async function formWalk() {
  renderWithPlugins(DiagnosticView, {
    global: {
      components: {
        Button,
        Checkbox,
        InputText,
        MChoiceGroup,
        MDiagnosticContactForm,
        MDiagnosticProgress,
        MDiagnosticResult,
        MImpactSimulator,
      },
      stubs: {
        MSlotPicker: true,
        RouterLink: { template: '<a><slot /></a>' },
        ThePublicLayout: { template: '<div><slot /></div>' },
      },
    },
  });

  await fireEvent.click(screen.getByTestId('path-quiz'));
  await waitStarted();
  await fireEvent.click(screen.getByTestId('sector-servicos'));
  await fireEvent.click(screen.getByTestId('regime-simples'));
  await fireEvent.click(screen.getByTestId('step-next'));
  await screen.findByTestId('contact-form');
  await fireEvent.update(screen.getByTestId('contact-company'), 'Clínica Horizonte');
  await fireEvent.update(screen.getByTestId('contact-name'), 'Ana Souza');
  await fireEvent.update(screen.getByTestId('contact-phone'), '(11) 98888-7777');
  await fireEvent.click(screen.getByTestId('contact-consent'));
  await fireEvent.click(screen.getByTestId('contact-save'));
  await screen.findByTestId('step-profile');
  await fireEvent.click(screen.getByTestId('b2b-80'));
  await fireEvent.click(screen.getByTestId('step-next'));
  await screen.findByTestId('step-revenue');
  await fireEvent.click(screen.getByTestId('band-b2'));
  await fireEvent.click(screen.getByTestId('step-next'));
  await screen.findByTestId('result');
}

beforeEach(() => {
  requests.length = 0;
  lookupAnswer.company = COMPANY_FIXTURE;
  lookupAnswer.status = 200;
  sendBeacon.mockClear();
  localStorage.clear();
  sessionStorage.clear();
  vi.stubGlobal('navigator', { ...navigator, sendBeacon });
  server.use(
    http.get('http://localhost/public/firm', () => HttpResponse.json({ data: { name: 'Franco Contabilidade' } })),
    http.post(API, async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;
      requests.push({ body, method: 'POST', path: 'start' });

      return HttpResponse.json({ data: { ...LEAD_FIXTURE, channel: body.channel, path: body.path } }, { status: 201 });
    }),
    http.post(`${API}/:token/cnpj`, async ({ request }) => {
      requests.push({ body: await request.json(), method: 'POST', path: 'cnpj' });

      if (lookupAnswer.status !== 200)
        return HttpResponse.json({ message: 'invalid' }, { status: lookupAnswer.status });

      return HttpResponse.json({
        data: {
          company: lookupAnswer.company,
          lead: { ...LEAD_FIXTURE, profile: { ...LEAD_FIXTURE.profile, regime: 'simples', sector: 'comercio' } },
          lookup: lookupAnswer.company ? 'found' : 'unavailable',
        },
      });
    }),
    http.patch(`${API}/:token`, async ({ request }) => {
      requests.push({ body: await request.json(), method: 'PATCH', path: 'update' });

      return HttpResponse.json({ data: { ...LEAD_FIXTURE, token: undefined } });
    }),
    http.get(`${API}/:token/result`, () => HttpResponse.json({ data: RESULT_FIXTURE })),
    http.get(`${API}/:token/simulation`, () => HttpResponse.json({ data: SIMULATION_FIXTURE }))
  );
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

describe('useChatReceptionist — A10, the same PATCHes as the form (AM8)', () => {
  it('sends, for "Simples, B2B 80 %, serviços", the PATCH sequence the form sends', async () => {
    await formWalk();
    const fromForm = patches();

    requests.length = 0;
    sendBeacon.mockClear();

    const { chat } = mountChat();

    await chatWalk(chat);

    expect(fromForm).toHaveLength(4);
    expect(patches()).toEqual(fromForm);
  });

  it('starts the lead as a chat lead, and the form starts it as a form lead', async () => {
    const { chat } = mountChat();

    await type(chat, 'não tenho');
    await waitStarted();

    expect(startBody()).toMatchObject({ channel: 'chat', path: 'quiz' });

    requests.length = 0;
    sendBeacon.mockClear();
    await formWalk();

    expect(startBody()).toMatchObject({ channel: 'formulario', path: 'quiz' });
  });
});

describe('useChatReceptionist — the talk (R12–R13)', () => {
  it('greets, asks the path and keeps the answer and the question in the transcript', async () => {
    const { chat } = mountChat();

    expect(chat.log.value.map((line) => line.key)).toEqual(['diagnostic.chat.greeting']);
    expect(chat.prompt.value?.ask).toBe('diagnostic.chat.asks.path');

    await reply(chat, 'quiz');

    expect(chat.log.value.slice(1)).toMatchObject([
      { key: 'diagnostic.chat.asks.path', speaker: 'reception' },
      { speaker: 'visitor', text: "I don't have the CNPJ" },
    ]);
    expect(chat.turn.value).toBe(CHAT_TURN.SECTOR);
  });

  it('answers "Não entendi…" to words that match no option and stays on the turn (A11)', async () => {
    const { chat } = mountChat();

    await reply(chat, 'quiz');
    await waitFor(() => expect(chat.isBusy.value).toBe(false));
    chat.typed.value = 'não sei de nada';
    chat.send();

    expect(chat.turn.value).toBe(CHAT_TURN.SECTOR);
    expect(chat.errorKey.value).toBe('diagnostic.chat.notUnderstood');
    expect(chat.log.value[chat.log.value.length - 1]).toMatchObject({ speaker: 'visitor', text: 'não sei de nada' });

    await type(chat, 'saude');

    expect(chat.errorKey.value).toBeNull();
    expect(chat.turn.value).toBe(CHAT_TURN.REGIME);
  });

  it("refuses a contact it cannot read with the demo's words, and stays on the turn (A11)", async () => {
    const { chat, diagnostic } = mountChat();

    await reply(chat, 'quiz');
    await waitStarted();
    await reply(chat, 'saude');
    await reply(chat, 'simples');
    await type(chat, 'Clínica Horizonte');
    await type(chat, 'Ana');
    await waitFor(() => expect(chat.isBusy.value).toBe(false));
    chat.typed.value = '1234';
    chat.send();

    expect(chat.turn.value).toBe(CHAT_TURN.CONTACT);
    expect(chat.errorKey.value).toBe('diagnostic.chat.contactInvalid');
    expect(diagnostic.contact.value.phone).toBe('');
    expect(patches()).toHaveLength(1);
  });

  it('confirms the company the CNPJ lookup found, then asks for the contact', async () => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
    const { chat, diagnostic } = mountChat();

    await reply(chat, 'cnpj');
    await waitStarted();
    await waitFor(() => expect(chat.isBusy.value).toBe(false));
    chat.typed.value = '11.222.333/0001-81';
    chat.send();

    expect(chat.turn.value).toBe(CHAT_TURN.LOOKUP);
    expect(chat.isBusy.value).toBe(true);

    await vi.advanceTimersByTimeAsync(2000);
    await waitFor(() => expect(diagnostic.lookupStatus.value).toBe(LOOKUP_STATUS.FOUND));

    expect(chat.prompt.value?.askParams.name).toBe(COMPANY_FIXTURE.name);

    await reply(chat, 'sim');

    expect(chat.turn.value).toBe(CHAT_TURN.COMPANY);
    expect(chat.prompt.value?.replies).toEqual([{ text: COMPANY_FIXTURE.name, value: COMPANY_FIXTURE.name }]);
    expect(patches()[0]).toEqual({ profile: { regime: 'simples', sector: 'comercio' }, step: 'contato' });
  });

  it('sends a CNPJ that does not check out back to the CNPJ turn', async () => {
    const { chat } = mountChat();

    await reply(chat, 'cnpj');
    await waitStarted();
    await waitFor(() => expect(chat.isBusy.value).toBe(false));
    chat.typed.value = '1234';
    chat.send();

    expect(chat.turn.value).toBe(CHAT_TURN.CNPJ);
    expect(chat.errorKey.value).toBe('diagnostic.cnpj.invalid');
    expect(requests.some((request) => request.path === 'cnpj')).toBe(false);
  });
});

describe('useChatReceptionist — switching with the form mid-way (R14)', () => {
  it('picks up where the form left the diagnostic, and says so', async () => {
    const isActive = ref(false);
    const { chat, diagnostic } = mountChat(isActive);

    diagnostic.choosePath(DIAGNOSTIC_PATH.QUIZ);
    await waitStarted();
    diagnostic.profile.value.sector = 'servicos';
    diagnostic.profile.value.regime = 'simples';
    diagnostic.confirmCompany();
    await waitFor(() => expect(diagnostic.step.value).toBe(DIAGNOSTIC_STEP.CONTACT));
    diagnostic.contact.value.company = 'Clínica Horizonte';

    isActive.value = true;
    await waitFor(() => expect(chat.log.value).toHaveLength(2));

    expect(chat.log.value.map((line) => line.key)).toEqual(['diagnostic.chat.greeting', 'diagnostic.chat.resumed']);
    expect(chat.turn.value).toBe(CHAT_TURN.NAME);
  });

  it('keeps the contact typed in the chat for the form, and moves on when the form saves a step', async () => {
    const isActive = ref(true);
    const { chat, diagnostic } = mountChat(isActive);

    await reply(chat, 'quiz');
    await waitStarted();
    await reply(chat, 'servicos');
    await reply(chat, 'simples');
    await type(chat, 'Clínica Horizonte');

    isActive.value = false;

    expect(diagnostic.contact.value.company).toBe('Clínica Horizonte');

    diagnostic.contact.value.phone = '(11) 98888-7777';
    diagnostic.saveContact();
    await waitFor(() => expect(diagnostic.step.value).toBe(DIAGNOSTIC_STEP.PROFILE));

    expect(chat.turn.value).toBe(CHAT_TURN.B2B);
    expect(chat.log.value[chat.log.value.length - 1]?.key).toBe('diagnostic.chat.advanced');
  });

  it('starts over with the diagnostic', async () => {
    const { chat, diagnostic } = mountChat();

    await reply(chat, 'quiz');
    await waitStarted();
    diagnostic.restart();
    await waitFor(() => expect(chat.turn.value).toBe(CHAT_TURN.PATH));

    expect(chat.log.value.map((line) => line.key)).toEqual(['diagnostic.chat.greeting']);
  });
});
