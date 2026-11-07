import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import Textarea from 'primevue/textarea';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import MLeadCard from '../../components/molecules/MLeadCard/MLeadCard.vue';
import MLeadChannelChip from '../../components/molecules/MLeadChannelChip/MLeadChannelChip.vue';
import MLostReasonForm from '../../components/molecules/MLostReasonForm/MLostReasonForm.vue';
import { LEAD_DRAG_TYPE } from '../../constants';
import { LEAD_STAGE } from '../../enums';
import { BOARD_FIXTURE, moveFixture } from '../../test/fixtures';
import PipelineView from './PipelineView.view.vue';

/*
 * The team's board (rnc-1 F5, R18, A11): a column per stage, cards moved by ◀ ▶ or by drag — each
 * move one PATCH with its `via` — and Perdido only after a reason.
 */
const API = 'http://localhost/leads';
const moves: { body: Record<string, unknown>; id: string }[] = [];

const STUBS = {
  AFormError: { props: ['error'], template: '<p v-if="error" data-testid="form-error">{{ error }}</p>' },
  MMainDialog: { props: ['visible'], template: '<div v-if="visible" data-testid="dialog"><slot /></div>' },
  RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot :navigate="() => {}" /></a>' },
};

function renderBoard(permissions = ['leads.read', 'leads.update', 'agenda.read']) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 3, last_name: 'Gomes', name: 'Patrícia', role: 'Equipe', role_slug: 'equipe', username: 'patricia' },
    permissions
  );

  return renderWithPlugins(PipelineView, {
    global: {
      components: {
        Button,
        IconField,
        InputIcon,
        InputText,
        MLeadCard,
        MLeadChannelChip,
        MLostReasonForm,
        Skeleton,
        Textarea,
      },
      stubs: STUBS,
    },
    pinia,
  });
}

function column(stage: LEAD_STAGE): HTMLElement {
  return screen.getAllByTestId('column').find((element) => element.dataset.stage === stage)!;
}

function countOf(stage: LEAD_STAGE): string {
  return within(column(stage)).getByTestId('column-count').textContent?.trim() ?? '';
}

beforeEach(() => {
  moves.length = 0;
  server.use(
    http.get(API, () => HttpResponse.json({ data: structuredClone(BOARD_FIXTURE) })),
    http.patch(`${API}/:id/stage`, async ({ params, request }) => {
      const body = (await request.json()) as Record<string, unknown>;

      moves.push({ body, id: String(params.id) });

      return HttpResponse.json({
        data: moveFixture(body.stage as LEAD_STAGE, String(body.via), body.reason as string),
      });
    })
  );
});

describe('PipelineView', () => {
  it('shows a column per stage, in order, with its count and cards', async () => {
    renderBoard();

    await screen.findAllByTestId('lead-card');

    expect(screen.getAllByTestId('column').map((element) => element.dataset.stage)).toEqual([
      'visitante',
      'lead_parcial',
      'lead',
      'reuniao_marcada',
      'proposta',
      'cliente',
      'perdido',
    ]);
    expect(countOf(LEAD_STAGE.LEAD)).toBe('1');
    expect(within(column(LEAD_STAGE.LEAD)).getByTestId('lead-card').textContent).toContain('3 opportunities · 2 risks');
    expect(within(column(LEAD_STAGE.LEAD_PARCIAL)).getByTestId('lead-card').textContent).toContain(
      'Stopped at: profile'
    );
  });

  it('moves a card forward by its button and lands it in the next column', async () => {
    renderBoard();

    const card = await waitFor(() => within(column(LEAD_STAGE.LEAD)).getByTestId('lead-card'));

    await fireEvent.click(within(card).getByTestId('move-next'));

    await waitFor(() => expect(moves).toEqual([{ body: { stage: 'reuniao_marcada', via: 'botao' }, id: '7' }]));
    await waitFor(() =>
      expect(within(column(LEAD_STAGE.REUNIAO_MARCADA)).queryAllByTestId('lead-card')).toHaveLength(1)
    );
    expect(countOf(LEAD_STAGE.LEAD)).toBe('0');
    expect(countOf(LEAD_STAGE.REUNIAO_MARCADA)).toBe('1');
  });

  it('moves a card dragged onto another column, saying it was dragged', async () => {
    renderBoard();

    await screen.findAllByTestId('lead-card');

    const store = new Map<string, string>();
    const dataTransfer = {
      dropEffect: 'none',
      effectAllowed: 'all',
      getData: (type: string) => store.get(type) ?? '',
      setData: (type: string, value: string) => store.set(type, value),
      get types() {
        return [...store.keys()];
      },
    };

    await fireEvent.dragStart(within(column(LEAD_STAGE.LEAD)).getByTestId('lead-card'), { dataTransfer });
    expect(store.get(LEAD_DRAG_TYPE)).toBe('7');

    await fireEvent.dragOver(column(LEAD_STAGE.PROPOSTA), { dataTransfer });
    await fireEvent.drop(column(LEAD_STAGE.PROPOSTA), { dataTransfer });

    await waitFor(() => expect(moves).toEqual([{ body: { stage: 'proposta', via: 'arrastar' }, id: '7' }]));
    await waitFor(() => expect(within(column(LEAD_STAGE.PROPOSTA)).queryAllByTestId('lead-card')).toHaveLength(1));
  });

  it('asks the reason before Perdido, and refuses to send without one', async () => {
    renderBoard();

    const card = await waitFor(() => within(column(LEAD_STAGE.LEAD)).getByTestId('lead-card'));

    await fireEvent.click(within(card).getByTestId('move-lost'));

    expect(await screen.findByTestId('lost-reason-form')).toBeTruthy();
    expect(moves).toHaveLength(0);

    await fireEvent.submit(screen.getByTestId('lost-reason-form'));

    expect((await screen.findByTestId('form-error')).textContent).toContain('Say why the lead was lost.');
    expect(moves).toHaveLength(0);

    await fireEvent.update(screen.getByTestId('lost-reason'), 'Sem orçamento este ano');
    await fireEvent.submit(screen.getByTestId('lost-reason-form'));

    await waitFor(() =>
      expect(moves).toEqual([{ body: { reason: 'Sem orçamento este ano', stage: 'perdido', via: 'botao' }, id: '7' }])
    );
    await waitFor(() => expect(screen.queryByTestId('dialog')).toBeNull());
    expect(within(column(LEAD_STAGE.PERDIDO)).getByTestId('lead-lost-reason').textContent).toContain(
      'Sem orçamento este ano'
    );
  });

  it('offers no moves to whoever cannot change leads', async () => {
    renderBoard(['leads.read']);

    const card = await waitFor(() => within(column(LEAD_STAGE.LEAD)).getByTestId('lead-card'));

    expect(within(card).queryByTestId('move-next')).toBeNull();
    expect(card.getAttribute('draggable')).toBe('false');
    expect(screen.queryByTestId('open-agenda')).toBeNull();
  });

  it('says the board did not load, and offers to try again', async () => {
    server.use(http.get(API, () => HttpResponse.json({ message: 'boom' }, { status: 500 })));

    renderBoard();

    expect((await screen.findByTestId('pipeline-failed')).textContent).toContain('The pipeline could not be loaded.');
  });
});
