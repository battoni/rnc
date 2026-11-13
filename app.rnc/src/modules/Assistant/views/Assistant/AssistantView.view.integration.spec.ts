import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Skeleton from 'primevue/skeleton';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { AssistantAnswer } from '../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import i18n from '@Libraries/i18n';
import { useUserStore } from '@UserModule';
import { MAnswerCard, MHandoffCard } from '../../components';
import {
  ANSWERED_FIXTURE,
  CARLOS,
  OPEN_QUESTION_FIXTURE,
  SUGGESTIONS_FIXTURE,
  UNANSWERED_FIXTURE,
} from '../../test/fixtures';
import AssistantView from './AssistantView.view.vue';

/*
 * "Assistente da Reforma" (rnc-3 R12, R13; A13's screen half, A14's): the team asks, and the answer is
 * one of Franco's reviewed notes — its text, "Regra", "Fonte …, lida em …" and "revisado por … em …" —
 * or, with no served note, the fixed sentence (C4) and the hand-off to a specialist the person picks.
 * The chips ask by note id. The transcript lives in the screen only.
 */
const asked: unknown[] = [];
const handed: unknown[] = [];
let answer: AssistantAnswer = ANSWERED_FIXTURE;

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = { RouterLink: { props: ['to'], template: '<a data-testid="router-link"><slot /></a>' } };

function renderAssistant(keys = ['assistant.ask'], isSpecialist = false) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    {
      id: 2,
      is_specialist: isSpecialist,
      last_name: 'Gomes',
      name: 'Patrícia',
      role: 'Equipe',
      role_slug: 'equipe',
      username: 'patricia',
    },
    keys
  );

  return renderWithPlugins(AssistantView, {
    global: { components: { Button, InputText, MAnswerCard, MHandoffCard, Select, Skeleton }, stubs: STUBS },
    pinia,
  });
}

/** The text as read, spaces collapsed. */
function plain(node: Element | null | undefined): string {
  return (node?.textContent ?? '').replace(/\s+/g, ' ').trim();
}

async function ask(text: string) {
  await fireEvent.update(screen.getByTestId('assistant-input'), text);
  await fireEvent.click(screen.getByTestId('assistant-send'));
}

beforeEach(() => {
  asked.length = 0;
  handed.length = 0;
  answer = ANSWERED_FIXTURE;
  server.use(
    http.get('http://localhost/assistant/suggestions', () => HttpResponse.json({ data: SUGGESTIONS_FIXTURE })),
    http.post('http://localhost/assistant/ask', async ({ request }) => {
      asked.push(await request.json());

      return HttpResponse.json({ data: answer });
    }),
    http.post('http://localhost/assistant/questions', async ({ request }) => {
      const body = (await request.json()) as { question: string; specialist_id: number };
      handed.push(body);

      return HttpResponse.json(
        { data: { ...OPEN_QUESTION_FIXTURE, question: body.question, specialist: CARLOS } },
        { status: 201 }
      );
    })
  );
});

afterEach(() => {
  i18n.global.locale.value = 'en';
});

describe('AssistantView — an answer from a reviewed note (R12, A14)', () => {
  it('greets and offers the served notes as chips', async () => {
    renderAssistant();

    expect(plain(screen.getByTestId('assistant-greeting'))).toContain(
      'I answer only from notes the specialists reviewed'
    );
    await waitFor(() => expect(screen.getAllByTestId('assistant-suggestion')).toHaveLength(2));
    expect(plain(screen.getAllByTestId('assistant-suggestion')[0])).toContain('Quando começa a cobrança da CBS?');
  });

  it('answers a question with the note, its rule, its source and who reviewed it', async () => {
    renderAssistant();

    await ask('  quando começa a cobrança da CBS  ');

    const card = await screen.findByTestId('answer-card');

    expect(asked).toEqual([{ question: 'quando começa a cobrança da CBS' }]);
    expect(plain(screen.getAllByTestId('assistant-message-me')[0])).toBe('quando começa a cobrança da CBS');
    expect(plain(card)).toContain('A CBS começa a ser cobrada em 2027');
    expect(plain(within(card).getByTestId('answer-rule'))).toBe('Rule: LC 214/2025, art. 343');
    expect(plain(within(card).getByTestId('answer-source'))).toBe(
      'Source: Barbieri Advogados — Cronograma da Reforma, read on 05/11/2026'
    );
    expect(plain(within(card).getByTestId('answer-reviewer'))).toBe('reviewed by Mariana Toledo on 05/11/2026');
    expect(plain(screen.getByTestId('assistant-count'))).toBe(
      '1 answer in this conversation, all from reviewed notes.'
    );
  });

  it('asks a chip by its note id', async () => {
    renderAssistant();

    await fireEvent.click((await screen.findAllByTestId('assistant-suggestion'))[0]!);

    await screen.findByTestId('answer-card');
    expect(asked).toEqual([{ note_id: 1, question: 'Quando começa a cobrança da CBS?' }]);
  });

  it('does not send a question under 3 characters, and says why', async () => {
    renderAssistant();

    await ask(' ab ');

    expect(plain(screen.getByTestId('assistant-input-error'))).toBe('Write at least 3 characters.');
    expect(asked).toEqual([]);
  });
});

describe('AssistantView — one conversation', () => {
  it('takes the next question as soon as an answer lands', async () => {
    renderAssistant();

    await ask('quando começa a cobrança da CBS');
    await screen.findByTestId('answer-card');
    answer = UNANSWERED_FIXTURE;
    await ask('qual o melhor ERP');

    await screen.findByTestId('handoff-card');
    expect(asked).toHaveLength(2);
  });
});

describe('AssistantView — no reviewed note: the hand-off (R13, A13, A14)', () => {
  beforeEach(() => {
    answer = UNANSWERED_FIXTURE;
  });

  it('says the fixed sentence, never an answer of its own, and offers the hand-off', async () => {
    renderAssistant();

    await ask('qual o melhor ERP');

    const card = await screen.findByTestId('handoff-card');

    expect(plain(card)).toContain(
      'There is no note reviewed by the team for this question, so I will not risk an answer.'
    );
    expect(screen.queryByTestId('answer-card')).toBeNull();
    expect(screen.queryByTestId('assistant-count')).toBeNull();
    expect(plain(within(card).getByTestId('handoff-send'))).toBe('Hand to the specialist');
  });

  it('says the sentence in pt-BR as the demo wrote it (C4)', async () => {
    i18n.global.locale.value = 'pt-BR';
    renderAssistant();

    await ask('qual o melhor ERP');

    expect(plain(await screen.findByTestId('handoff-card'))).toContain(
      'Não há nota revisada pela equipe para essa pergunta, então não vou arriscar uma resposta.'
    );
  });

  it('hands the question to the first specialist by name unless another is picked, then says who has it', async () => {
    renderAssistant();

    await ask('qual o melhor ERP');
    await fireEvent.click(within(await screen.findByTestId('handoff-card')).getByTestId('handoff-send'));

    await waitFor(() => expect(handed).toEqual([{ question: 'qual o melhor ERP', specialist_id: CARLOS.id }]));
    expect(plain(await screen.findByTestId('handoff-done'))).toBe(
      'Handed to Carlos Prado. The answer arrives on the notifications bell.'
    );
    expect(screen.queryByTestId('handoff-send')).toBeNull();
  });
});

describe('AssistantView — the way to the inbox (R14)', () => {
  it('links a specialist to the questions handed to them', () => {
    renderAssistant(['assistant.ask'], true);

    expect(plain(screen.getByTestId('assistant-questions-link'))).toBe('Questions for specialists');
  });

  it('shows no inbox link to a member who is neither specialist nor manager', () => {
    renderAssistant();

    expect(screen.queryByTestId('assistant-questions-link')).toBeNull();
  });
});
