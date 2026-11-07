import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { beforeEach, describe, expect, it } from 'vitest';
import type { KnowledgeNote } from '../../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { KNOWLEDGE_NOTES_FIXTURE } from '../../../test/fixtures';
import MAddEditNoteForm from './MAddEditNoteForm.vue';

/*
 * A note of "Base da Reforma" written or edited (rnc-3 R10, D7): the question, the answer, the legal
 * rule, the source and the day it was read, and the strong and weak keys the matcher scores. A new
 * note is a rascunho; editing a reviewed one sends it back to rascunho, and the form says so before
 * saving. "Virar nota" (R14) opens it prefilled with the question and the answer and sends
 * `from_question`. The limits are refused here before the API refuses them.
 */
const CBS = KNOWLEDGE_NOTES_FIXTURE[0] as KnowledgeNote;

const sent: { body: Record<string, unknown>; method: string; url: string }[] = [];
let postStatus = 201;

function renderForm(props: Record<string, unknown> = {}) {
  return renderWithPlugins(MAddEditNoteForm, { global: { components: { Button, InputText, Textarea } }, props });
}

function plain(node: Element | null | undefined): string {
  return (node?.textContent ?? '').replace(/\s+/g, ' ').trim();
}

async function fill(values: Record<string, string>) {
  for (const [testId, value] of Object.entries(values)) {
    await fireEvent.update(screen.getByTestId(testId), value);
  }
}

const COMPLETE = {
  'note-answer': 'O IPI tem alíquota zerada a partir de 2027.',
  'note-as-of': '2026-11-05',
  'note-question': 'Como fica o IPI na Reforma?',
  'note-rule': 'EC 132/2023',
  'note-source-label': 'gov.br/fazenda — Reforma Tributária',
  'note-source-url': 'https://www.gov.br/fazenda',
  'note-strong': 'ipi, ',
  'note-weak': ' manaus , zona,zona',
};

beforeEach(() => {
  sent.length = 0;
  postStatus = 201;
  server.use(
    http.post('http://localhost/assistant/notes', async ({ request }) => {
      const body = (await request.json()) as Record<string, unknown>;
      sent.push({ body, method: 'POST', url: request.url });

      if (postStatus === 422) {
        return HttpResponse.json({ code: 'already_noted', error: 'assistant.alreadyNoted' }, { status: 422 });
      }

      return HttpResponse.json(
        { data: { ...CBS, id: 9, question: body.question, status: 'rascunho' } },
        { status: 201 }
      );
    }),
    http.patch('http://localhost/assistant/notes/:id', async ({ request }) => {
      sent.push({ body: (await request.json()) as Record<string, unknown>, method: 'PATCH', url: request.url });

      return HttpResponse.json({ data: { ...CBS, review_state: 'rascunho', status: 'rascunho' } });
    })
  );
});

describe('MAddEditNoteForm — a new note (R10)', () => {
  it('sends the content with the keys split, trimmed and without repeats', async () => {
    const { emitted } = renderForm();

    await fill(COMPLETE);
    await fireEvent.submit(screen.getByTestId('note-form'));

    await waitFor(() => expect(sent).toHaveLength(1));
    expect(sent[0]).toMatchObject({
      body: {
        answer: 'O IPI tem alíquota zerada a partir de 2027.',
        as_of: '2026-11-05',
        keywords_strong: ['ipi'],
        keywords_weak: ['manaus', 'zona'],
        question: 'Como fica o IPI na Reforma?',
        rule: 'EC 132/2023',
        source_label: 'gov.br/fazenda — Reforma Tributária',
        source_url: 'https://www.gov.br/fazenda',
      },
      method: 'POST',
    });
    expect(sent[0]!.body).not.toHaveProperty('from_question');
    await waitFor(() => expect(emitted().onSuccess).toHaveLength(1));
  });

  it('refuses before the call: empty fields, an address that is not http(s), no strong key, a key of one letter', async () => {
    renderForm();

    await fill({
      ...COMPLETE,
      'note-question': ' ',
      'note-source-url': 'ftp://x',
      'note-strong': ' , ',
      'note-weak': 'a',
    });
    await fireEvent.submit(screen.getByTestId('note-form'));

    expect(plain(screen.getByTestId('note-form'))).toContain('Required.');
    expect(plain(screen.getByTestId('note-form'))).toContain('An address starting with http:// or https://.');
    expect(plain(screen.getByTestId('note-form'))).toContain('At least one strong key.');
    expect(plain(screen.getByTestId('note-form'))).toContain('Each key has 2 to 40 characters, at most 12 of them.');
    expect(sent).toEqual([]);
  });

  it('refuses a day in the future', async () => {
    renderForm();

    await fill({ ...COMPLETE, 'note-as-of': '2999-01-01' });
    await fireEvent.submit(screen.getByTestId('note-form'));

    expect(plain(screen.getByTestId('note-form'))).toContain('A day that is not in the future.');
    expect(sent).toEqual([]);
  });
});

describe('MAddEditNoteForm — "Virar nota" (R14)', () => {
  it('starts on the question and the answer and sends from_question', async () => {
    renderForm({ fromQuestion: 12, prefill: { answer: 'Depende do volume.', question: 'O Simples perde crédito?' } });

    expect((screen.getByTestId('note-question') as HTMLInputElement).value).toBe('O Simples perde crédito?');
    expect((screen.getByTestId('note-answer') as HTMLTextAreaElement).value).toBe('Depende do volume.');

    await fill({ ...COMPLETE, 'note-answer': 'Depende do volume.', 'note-question': 'O Simples perde crédito?' });
    await fireEvent.submit(screen.getByTestId('note-form'));

    await waitFor(() => expect(sent[0]?.body.from_question).toBe(12));
  });

  it('says the API refusal by its key: the question already became a note', async () => {
    postStatus = 422;
    renderForm({ fromQuestion: 12, prefill: { answer: 'Depende do volume.', question: 'O Simples perde crédito?' } });

    await fill(COMPLETE);
    await fireEvent.submit(screen.getByTestId('note-form'));

    await waitFor(() => expect(plain(screen.getByTestId('note-form'))).toContain('This question is already a note.'));
  });
});

describe('MAddEditNoteForm — an edit (D7)', () => {
  it('warns that saving a change sends a reviewed note back to draft, and sends a PATCH', async () => {
    const { emitted } = renderForm({ note: CBS });

    expect(plain(screen.getByTestId('note-back-to-draft'))).toBe(
      'Saving a change sends the note back to draft: another specialist reviews it again.'
    );
    expect((screen.getByTestId('note-strong') as HTMLInputElement).value).toBe('cbs');
    expect((screen.getByTestId('note-weak') as HTMLInputElement).value).toBe('cobranca, comeca');

    await fill({ 'note-answer': 'Nova resposta.' });
    await fireEvent.submit(screen.getByTestId('note-form'));

    await waitFor(() => expect(sent[0]?.method).toBe('PATCH'));
    expect(sent[0]!.url).toContain('/assistant/notes/1');
    expect(sent[0]!.body.answer).toBe('Nova resposta.');
    await waitFor(() => expect(emitted().onSuccess).toHaveLength(1));
  });

  it('does not warn on a draft', () => {
    renderForm({ note: { ...CBS, review_state: 'rascunho', status: 'rascunho' } });

    expect(screen.queryByTestId('note-back-to-draft')).toBeNull();
  });
});
