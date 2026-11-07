import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Textarea from 'primevue/textarea';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { AssistantQuestion } from '../../interfaces';
import AEmptyState from '@/components/atoms/AEmptyState/AEmptyState.vue';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import {
  ANSWERED_QUESTION_FIXTURE,
  CARLOS_QUESTION_FIXTURE,
  MARIANA,
  OPEN_QUESTION_FIXTURE,
} from '../../test/fixtures';
import QuestionsView from './QuestionsView.view.vue';

/*
 * "Perguntas para especialista" (rnc-3 R14; A13's screen half, A14's "the inbox answers"): the
 * specialist answers the questions handed to them — the asker's bell rings — and "Virar nota" opens
 * a rascunho prefilled with the question and the answer. `assistant.manage` reads every question;
 * only the assigned specialist answers.
 */
const answered: unknown[] = [];
let inbox: AssistantQuestion[] = [];
let inboxStatus = 200;

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  MAddEditNoteForm: {
    emits: ['onSuccess'],
    props: ['fromQuestion', 'prefill'],
    template:
      '<div data-testid="note-form" :data-from="fromQuestion" :data-question="prefill?.question" :data-answer="prefill?.answer"><button data-testid="note-form-save" @click="$emit(\'onSuccess\', { id: 77 })">save</button></div>',
  },
  MMainDialog: { props: ['visible'], template: '<div v-if="visible" data-testid="dialog"><slot /></div>' },
};

function renderInbox(user: { id: number; is_specialist: boolean }, keys = ['assistant.ask']) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { ...user, last_name: 'Pessoa', name: 'Uma', role: 'Equipe', role_slug: 'equipe', username: 'uma' },
    keys
  );

  return renderWithPlugins(QuestionsView, {
    global: { components: { AEmptyState, Button, Skeleton, Textarea }, stubs: STUBS },
    pinia,
  });
}

function plain(node: Element | null | undefined): string {
  return (node?.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function rowOf(id: number) {
  return screen.getAllByTestId('question-row').find((row) => row.dataset.id === String(id))!;
}

beforeEach(() => {
  answered.length = 0;
  inbox = [OPEN_QUESTION_FIXTURE, ANSWERED_QUESTION_FIXTURE];
  inboxStatus = 200;
  server.use(
    http.get('http://localhost/assistant/questions', () =>
      inboxStatus === 200
        ? HttpResponse.json({ data: structuredClone(inbox) })
        : HttpResponse.json({ code: 'not_writer', error: 'assistant.notWriter' }, { status: inboxStatus })
    ),
    http.post('http://localhost/assistant/questions/:id/answer', async ({ params, request }) => {
      const body = (await request.json()) as { answer: string };
      answered.push({ id: Number(params.id), ...body });

      return HttpResponse.json({
        data: {
          ...OPEN_QUESTION_FIXTURE,
          answer: body.answer,
          answered_at: '2026-11-19T15:00:00+00:00',
          status: 'respondida',
        },
      });
    })
  );
});

describe('QuestionsView — the specialist answers (A13, A14)', () => {
  it('lists the questions with the status, who asked and who holds it', async () => {
    renderInbox({ id: MARIANA.id, is_specialist: true });

    await waitFor(() => expect(screen.getAllByTestId('question-row')).toHaveLength(2));

    expect(plain(rowOf(11))).toContain('Qual o melhor ERP para o Simples?');
    expect(plain(within(rowOf(11)).getByTestId('question-status'))).toBe('Open');
    expect(plain(rowOf(11))).toContain('from Patrícia Gomes to Mariana Toledo');
    expect(plain(within(rowOf(12)).getByTestId('question-status'))).toBe('Answered');
    expect(plain(within(rowOf(12)).getByTestId('question-answer'))).toContain('Depende do volume de notas');
  });

  it('answers an open question of theirs, and the row turns answered without a reload', async () => {
    renderInbox({ id: MARIANA.id, is_specialist: true });

    const row = await waitFor(() => rowOf(11));

    await fireEvent.update(within(row).getByTestId('question-answer-input'), '  Depende do volume de notas.  ');
    await fireEvent.click(within(row).getByTestId('question-answer-send'));

    await waitFor(() => expect(answered).toEqual([{ answer: 'Depende do volume de notas.', id: 11 }]));
    await waitFor(() => expect(plain(within(rowOf(11)).getByTestId('question-status'))).toBe('Answered'));
    expect(within(rowOf(11)).queryByTestId('question-answer-input')).toBeNull();
  });

  it('does not send an answer under 3 characters', async () => {
    renderInbox({ id: MARIANA.id, is_specialist: true });

    const row = await waitFor(() => rowOf(11));

    await fireEvent.update(within(row).getByTestId('question-answer-input'), 'ok');
    await fireEvent.click(within(row).getByTestId('question-answer-send'));

    expect(plain(within(row).getByTestId('question-answer-error'))).toBe('Write at least 3 characters.');
    expect(answered).toEqual([]);
  });

  it('offers no answer box on a question held by another specialist — Reinaldo reads it, waiting', async () => {
    inbox = [CARLOS_QUESTION_FIXTURE];
    renderInbox({ id: 1, is_specialist: false }, ['assistant.ask', 'assistant.manage']);

    const row = await waitFor(() => rowOf(13));

    expect(within(row).queryByTestId('question-answer-input')).toBeNull();
    expect(plain(row)).toContain('Waiting for Carlos Prado');
  });
});

describe('QuestionsView — "Virar nota" (R14, A13)', () => {
  it('opens a rascunho prefilled with the question and the answer, tied to the question', async () => {
    renderInbox({ id: MARIANA.id, is_specialist: true });

    await fireEvent.click(within(await waitFor(() => rowOf(12))).getByTestId('question-to-note'));

    const form = await screen.findByTestId('note-form');

    expect(form.dataset.from).toBe('12');
    expect(form.dataset.question).toBe('O Simples perde crédito na Reforma?');
    expect(form.dataset.answer).toBe('Depende do volume de notas; a Franco indica dois parceiros.');
  });

  it('marks the question as turned into a note once saved', async () => {
    renderInbox({ id: MARIANA.id, is_specialist: true });

    await fireEvent.click(within(await waitFor(() => rowOf(12))).getByTestId('question-to-note'));
    await fireEvent.click(await screen.findByTestId('note-form-save'));

    await waitFor(() => expect(within(rowOf(12)).queryByTestId('question-to-note')).toBeNull());
    expect(plain(within(rowOf(12)).getByTestId('question-noted'))).toBe('Turned into a note');
  });

  it('offers no "Virar nota" on an open question', async () => {
    renderInbox({ id: MARIANA.id, is_specialist: true });

    expect(within(await waitFor(() => rowOf(11))).queryByTestId('question-to-note')).toBeNull();
  });
});

describe('QuestionsView — who may read it', () => {
  it("says the inbox is the specialists' and the direction's on a 403", async () => {
    inboxStatus = 403;
    renderInbox({ id: 2, is_specialist: false });

    expect(plain(await screen.findByTestId('questions-forbidden'))).toBe(
      'Only the specialists and the direction see the questions.'
    );
  });

  it('says when there is nothing to answer', async () => {
    inbox = [];
    renderInbox({ id: MARIANA.id, is_specialist: true });

    expect(plain(await screen.findByTestId('questions-empty'))).toContain('No question yet');
  });
});
