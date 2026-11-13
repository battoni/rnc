import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { KnowledgeNote } from '../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import i18n from '@Libraries/i18n';
import { useUserStore } from '@UserModule';
import { GAPS_FIXTURE, KNOWLEDGE_NOTES_FIXTURE, MARIANA } from '../../test/fixtures';
import KnowledgeView from './KnowledgeView.view.vue';

/*
 * "Base da Reforma" (rnc-3 R10, R11, R15, AM4, AM19; A14's "Perguntas sem nota", A28's counter on
 * screen): every note with its state — rascunho, revisada, revisão vencida, aposentada — who reviewed it
 * and when, the days a review has left, "N notas vencem em 7 dias", and for `assistant.manage` the
 * last 30 days' questions no note answered, grouped. Review is offered only where the API says the
 * reader may (`can_review`); retiring is `assistant.manage`'s.
 */
const reviewed: number[] = [];
const retired: number[] = [];
let gapsReads = 0;
let notes: KnowledgeNote[] = KNOWLEDGE_NOTES_FIXTURE;
let notesStatus = 200;
let reviewStatus = 200;

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

vi.mock('primevue/useconfirm', () => ({
  useConfirm: () => ({ require: ({ accept }: { accept: () => void }) => accept() }),
}));

const STUBS = {
  MAddEditNoteForm: { props: ['note'], template: '<div data-testid="note-form" :data-note="note?.id ?? \'new\'" />' },
  MConfirmPopup: { template: '<div />' },
  MMainDialog: { props: ['visible'], template: '<div v-if="visible" data-testid="dialog"><slot /></div>' },
};

function renderBase(keys: string[], isSpecialist: boolean) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    {
      id: MARIANA.id,
      is_specialist: isSpecialist,
      last_name: 'Pessoa',
      name: 'Uma',
      role: 'Papel',
      role_slug: 'papel',
      username: 'uma',
    },
    keys
  );

  return renderWithPlugins(KnowledgeView, { global: { components: { Button, Skeleton }, stubs: STUBS }, pinia });
}

const asSpecialist = () => renderBase(['assistant.ask'], true);
const asReinaldo = () => renderBase(['assistant.ask', 'assistant.manage'], false);

function plain(node: Element | null | undefined): string {
  return (node?.textContent ?? '').replace(/\s+/g, ' ').trim();
}

function rowOf(id: number) {
  return screen.getAllByTestId('note-row').find((row) => row.dataset.id === String(id))!;
}

beforeEach(() => {
  reviewed.length = 0;
  retired.length = 0;
  gapsReads = 0;
  notes = KNOWLEDGE_NOTES_FIXTURE;
  notesStatus = 200;
  reviewStatus = 200;
  server.use(
    http.get('http://localhost/assistant/notes', () =>
      notesStatus === 200
        ? HttpResponse.json({ data: structuredClone(notes), expiring_count: 3 })
        : HttpResponse.json({ code: 'not_writer', error: 'assistant.notWriter' }, { status: notesStatus })
    ),
    http.get('http://localhost/assistant/gaps', () => {
      gapsReads += 1;

      return HttpResponse.json({ data: GAPS_FIXTURE });
    }),
    http.post('http://localhost/assistant/notes/:id/review', ({ params }) => {
      reviewed.push(Number(params.id));

      if (reviewStatus !== 200) {
        return HttpResponse.json({ code: 'not_reviewer', error: 'assistant.notReviewer' }, { status: reviewStatus });
      }

      const note = notes.find((row) => row.id === Number(params.id))!;

      return HttpResponse.json({
        data: {
          ...note,
          can_review: false,
          expires_in_days: 90,
          review_state: 'revisada',
          reviewer: MARIANA,
          status: 'revisada',
        },
      });
    }),
    http.post('http://localhost/assistant/notes/:id/retire', ({ params }) => {
      retired.push(Number(params.id));
      const note = notes.find((row) => row.id === Number(params.id))!;

      return HttpResponse.json({
        data: { ...note, expires_in_days: null, review_state: 'aposentada', status: 'aposentada' },
      });
    })
  );
});

afterEach(() => {
  i18n.global.locale.value = 'en';
});

describe('KnowledgeView — the notes (R10, R11)', () => {
  it('shows each note with its state, incl. "revisão vencida"', async () => {
    asSpecialist();

    await waitFor(() => expect(screen.getAllByTestId('note-row')).toHaveLength(5));

    expect([1, 2, 3, 4, 5].map((id) => plain(within(rowOf(id)).getByTestId('note-state')))).toEqual([
      'Reviewed',
      'Reviewed',
      'Draft',
      'Review expired',
      'Retired',
    ]);
  });

  it('says it in pt-BR: "Revisão vencida"', async () => {
    i18n.global.locale.value = 'pt-BR';
    asSpecialist();

    expect(plain(within(await waitFor(() => rowOf(4))).getByTestId('note-state'))).toBe('Revisão vencida');
  });

  it('says who reviewed a note and when, and the days left when 7 or fewer', async () => {
    asSpecialist();

    const split = await waitFor(() => rowOf(2));

    expect(plain(split)).toContain('reviewed by Mariana Toledo on 05/11/2026');
    expect(plain(within(split).getByTestId('note-expires'))).toBe('expires in 5 days');
    expect(within(rowOf(1)).queryByTestId('note-expires')).toBeNull();
    expect(plain(rowOf(3))).toContain('awaiting review · last edit by Reinaldo Franco');
  });

  it('offers "Revisar" only where the API lets the reader review, and reviews it in place', async () => {
    asSpecialist();

    await waitFor(() => expect(screen.getAllByTestId('note-row')).toHaveLength(5));

    expect(
      screen
        .getAllByTestId('note-review')
        .map((button) => button.closest('[data-testid="note-row"]')?.getAttribute('data-id'))
    ).toEqual(['3', '4']);

    await fireEvent.click(within(rowOf(3)).getByTestId('note-review'));

    await waitFor(() => expect(plain(within(rowOf(3)).getByTestId('note-state'))).toBe('Reviewed'));
    expect(reviewed).toEqual([3]);
  });

  it('keeps the note as it was when the review is refused', async () => {
    reviewStatus = 403;
    asSpecialist();

    await fireEvent.click(within(await waitFor(() => rowOf(3))).getByTestId('note-review'));

    await waitFor(() => expect(reviewed).toEqual([3]));
    expect(plain(within(rowOf(3)).getByTestId('note-state'))).toBe('Draft');
  });

  it('opens the form to write a note or edit one, and never on a retired note', async () => {
    asSpecialist();

    await fireEvent.click(within(await waitFor(() => rowOf(2))).getByTestId('note-edit'));
    expect((await screen.findByTestId('note-form')).dataset.note).toBe('2');
    expect(within(rowOf(5)).queryByTestId('note-edit')).toBeNull();
  });
});

describe('KnowledgeView — "N notas vencem em 7 dias" (A28)', () => {
  it('shows the count the API makes (reviews 83–90 days old), not one of its own', async () => {
    asSpecialist();

    expect(plain(await screen.findByTestId('knowledge-expiring'))).toBe('3 notes expire within 7 days');
  });

  it('says it in pt-BR, singular too', async () => {
    i18n.global.locale.value = 'pt-BR';
    server.use(
      http.get('http://localhost/assistant/notes', () => HttpResponse.json({ data: notes, expiring_count: 1 }))
    );
    asSpecialist();

    expect(plain(await screen.findByTestId('knowledge-expiring'))).toBe('1 nota vence em 7 dias');
  });
});

describe('KnowledgeView — assistant.manage (R10, R15, A14)', () => {
  it('lists "Perguntas sem nota" grouped, most asked first, with the count', async () => {
    asReinaldo();

    await waitFor(() => expect(screen.getAllByTestId('gap-row')).toHaveLength(2));

    const [first, second] = screen.getAllByTestId('gap-row');

    expect(plain(first)).toContain('Quando começa a CBS?');
    expect(plain(within(first!).getByTestId('gap-count'))).toBe('2×');
    expect(plain(second)).toContain('qual o melhor ERP');
  });

  it('retires a note after confirming, and the row says so', async () => {
    asReinaldo();

    await fireEvent.click(within(await waitFor(() => rowOf(2))).getByTestId('note-retire'));

    await waitFor(() => expect(plain(within(rowOf(2)).getByTestId('note-state'))).toBe('Retired'));
    expect(retired).toEqual([2]);
  });

  it('shows a specialist neither the gaps nor "Aposentar"', async () => {
    asSpecialist();

    await waitFor(() => expect(screen.getAllByTestId('note-row')).toHaveLength(5));

    expect(screen.queryByTestId('knowledge-gaps')).toBeNull();
    expect(screen.queryByTestId('note-retire')).toBeNull();
    expect(gapsReads).toBe(0);
  });

  it('says who writes the base on a 403', async () => {
    notesStatus = 403;
    renderBase(['assistant.ask'], false);

    expect(plain(await screen.findByTestId('knowledge-forbidden'))).toBe(
      'Only the specialists and the direction write the knowledge base.'
    );
  });
});
