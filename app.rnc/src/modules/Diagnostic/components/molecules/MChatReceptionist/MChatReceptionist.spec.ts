import { defineComponent, h, ref } from 'vue';
import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useChatReceptionist, useDiagnostic } from '../../../composables';
import {
  AVAILABILITY_FIXTURE,
  BOOKING_FIXTURE,
  LEAD_FIXTURE,
  RESULT_FIXTURE,
  SIMULATION_FIXTURE,
} from '../../../test/fixtures';
import MBookingConfirmation from '../MBookingConfirmation/MBookingConfirmation.vue';
import MChatResult from '../MChatResult/MChatResult.vue';
import MSlotPicker from '../MSlotPicker/MSlotPicker.vue';
import MChatReceptionist from './MChatReceptionist.vue';

/*
 * The chat on screen (rnc-2 F5, R12–R13): the reception's lines, the quick replies, the box that
 * takes typed text at every turn, the "Não entendi…" alert, the reception "typing" while the API
 * answers, then the result with its locked items, the slot picker and the confirmation — all over
 * the real `useDiagnostic` + `useChatReceptionist`, with the API in msw.
 */
const API = 'http://localhost/public/diagnostics';
const requests: { body: any; path: string }[] = [];
const sendBeacon = vi.fn<(url: string, body: Blob) => boolean>(() => true);

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const Host = defineComponent({
  emits: ['onSimulator'],
  setup(_, { emit }) {
    const diagnostic = useDiagnostic();
    const chat = useChatReceptionist(diagnostic, ref(true));

    return () => h(MChatReceptionist, { chat, diagnostic, onOnSimulator: () => emit('onSimulator') });
  },
});

function renderChat() {
  return renderWithPlugins(Host, {
    global: {
      components: { Button, InputText, MBookingConfirmation, MChatResult, MSlotPicker },
      stubs: { RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' } },
    },
  });
}

function lines() {
  return screen.getAllByTestId('chat-msg').map((line) => line.textContent?.replace(/\s+/g, ' ').trim());
}

async function tap(value: string) {
  const reply = await waitFor(() => {
    const found = screen.getAllByTestId('chat-reply').find((button) => button.getAttribute('data-value') === value);

    expect(found, `reply ${value}`).toBeDefined();

    return found!;
  });

  await fireEvent.click(reply);
}

async function say(text: string) {
  await waitFor(() => expect(screen.getByTestId('chat-input')).toBeTruthy());
  await fireEvent.update(screen.getByTestId('chat-input'), text);
  await fireEvent.submit(screen.getByTestId('chat-compose'));
}

/** Waits for the reception to stop "typing" and ask the next question. */
async function settled() {
  await waitFor(() => expect(screen.queryByTestId('chat-typing')).toBeNull());
}

beforeEach(() => {
  requests.length = 0;
  sendBeacon.mockClear();
  localStorage.clear();
  sessionStorage.clear();
  vi.stubGlobal('navigator', { ...navigator, sendBeacon });
  server.use(
    http.post(API, async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;
      requests.push({ body, path: 'start' });

      return HttpResponse.json({ data: { ...LEAD_FIXTURE, channel: body.channel } }, { status: 201 });
    }),
    http.patch(`${API}/:token`, async ({ request }) => {
      requests.push({ body: await request.json(), path: 'update' });

      return HttpResponse.json({ data: { ...LEAD_FIXTURE, token: undefined } });
    }),
    http.get(`${API}/:token/result`, () => HttpResponse.json({ data: RESULT_FIXTURE })),
    http.get(`${API}/:token/simulation`, () => HttpResponse.json({ data: SIMULATION_FIXTURE })),
    http.get(`${API}/:token/availability`, () => HttpResponse.json({ data: AVAILABILITY_FIXTURE })),
    http.post(`${API}/:token/booking`, () => HttpResponse.json({ data: BOOKING_FIXTURE }, { status: 201 }))
  );
});

afterEach(() => vi.unstubAllGlobals());

describe('MChatReceptionist — the talk (R12, R13)', () => {
  it('opens with the reception\'s greeting and the first question, signed "Recepção Franco"', () => {
    renderChat();

    expect(lines()).toEqual([expect.stringContaining("Franco's virtual reception")]);
    expect(screen.getByTestId('chat-live').textContent).toContain("do you have the company's CNPJ");
    expect(screen.getByTestId('chat-live').textContent).toContain('Franco reception');
    expect(screen.getByRole('log')).toBeTruthy();
    expect(screen.getAllByTestId('chat-reply').map((button) => button.getAttribute('data-value'))).toEqual([
      'cnpj',
      'quiz',
    ]);
  });

  it('starts a chat lead from the first answer and puts the answer in the transcript (A10)', async () => {
    renderChat();

    await tap('quiz');
    await waitFor(() => expect(requests.find((request) => request.path === 'start')).toBeDefined());

    expect(requests[0]!.body).toMatchObject({ channel: 'chat', path: 'quiz' });
    expect(lines().slice(-2)).toEqual([
      expect.stringContaining("do you have the company's CNPJ"),
      "I don't have the CNPJ",
    ]);
    await settled();
    expect(screen.getByTestId('chat-live').textContent).toContain('Which sector');
  });

  it('shows the reception typing while the API answers, without replies to tap twice', async () => {
    renderChat();

    await tap('quiz');

    expect(screen.getByTestId('chat-typing')).toBeTruthy();
    expect(screen.queryAllByTestId('chat-reply')).toHaveLength(0);

    await settled();

    expect(screen.getAllByTestId('chat-reply')).toHaveLength(6);
  });

  it('takes typed text at an option turn, and answers "Não entendi…" as an alert when it matches nothing (A11)', async () => {
    renderChat();

    await tap('quiz');
    await settled();
    await say('não sei de nada');

    const alert = screen.getByRole('alert');

    expect(alert.textContent).toContain('I did not understand. Choose one of the options below.');
    expect(screen.getByTestId('chat').getAttribute('data-turn')).toBe('sector');
    expect(screen.getAllByTestId('chat-reply')).toHaveLength(6);

    await say('saude');

    expect(screen.queryByRole('alert')).toBeNull();
    expect(screen.getByTestId('chat').getAttribute('data-turn')).toBe('regime');
  });

  it('keeps the send button off while the box is empty', async () => {
    renderChat();

    expect(screen.getByTestId('chat-send').hasAttribute('disabled')).toBe(true);

    await fireEvent.update(screen.getByTestId('chat-input'), 'tenho');

    expect(screen.getByTestId('chat-send').hasAttribute('disabled')).toBe(false);
  });
});

describe('MChatReceptionist — the result, the slot picker, the confirmation (R12)', () => {
  it('shows the locked result in the chat, then books through the same slot picker', async () => {
    const { emitted } = renderChat();

    await tap('quiz');
    await settled();
    await tap('servicos');
    await tap('simples');
    await settled();
    await say('Clínica Horizonte');
    await say('Ana Souza');
    await say('ana@horizonte.com.br');
    await tap('nao_autorizo');
    await settled();
    await say('80');
    await settled();
    await tap('b2');
    await tap('nao');
    await tap('nao');

    const result = await screen.findByTestId('chat-result');

    expect(within(result).getByTestId('result-summary').textContent).toMatch(/3 opportunities and 2 risks/);
    expect(within(result).getAllByTestId('result-item')).toHaveLength(5);
    expect(within(result).getAllByTestId('locked')).toHaveLength(5);
    expect(screen.queryByTestId('chat-input')).toBeNull();

    await fireEvent.click(within(result).getByTestId('chat-simulator'));

    expect(emitted().onSimulator).toHaveLength(1);

    await screen.findAllByTestId('specialist');
    // The contact was an e-mail, so the picker does not ask for one (AM5).
    expect(screen.queryByTestId('booking-email')).toBeNull();

    await fireEvent.click(screen.getAllByTestId('slot')[1]!);
    await fireEvent.click(screen.getByTestId('slot-confirm'));

    const confirmed = await screen.findByTestId('booking-confirmed');

    expect(confirmed.textContent).toContain('Juliana Reis');
    expect(JSON.parse(within(confirmed).getByTestId('open-report').getAttribute('data-to')!)).toMatchObject({
      params: { token: BOOKING_FIXTURE.report_token },
    });
    expect(requests.filter((request) => request.path === 'update').map((request) => request.body.step)).toEqual([
      'contato',
      'perfil',
      'faturamento',
      'resultado',
    ]);
  });
});
