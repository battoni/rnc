import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ChannelBoard } from '../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import i18n from '@Libraries/i18n';
import { useUserStore } from '@UserModule';
import { ADVANCED_BOARD_FIXTURE, CEO_BOARD_FIXTURE, OPERATOR_BOARD_FIXTURE } from '../../test/fixtures';
import ChannelView from './ChannelView.view.vue';

/*
 * The "Canal RNC" screen (rnc-3 F3b, R8, R9). The operator runs it: the hero of Franco's share, the
 * count per stage, a row per firm with its stepper, the platform's fee and the share, "Avançar", the
 * edit and the add. Reinaldo reads it (A9, AM15): his share per firm, the stage and the year total —
 * no control, no platform price ("R$ 3.500"), no "pela plataforma" lede. Neither screen names the
 * platform's owners or how they split the rest.
 */
const OPERATOR_KEYS = ['channel.read', 'channel.manage', 'users.read'];
const CEO_KEYS = ['channel.read', 'metrics.read'];
const OWNER_WORDS = ['Willy', 'APW', 'Battoni', 'divisão', 'split'];

const advanced: unknown[] = [];
let boardAnswer: ChannelBoard = OPERATOR_BOARD_FIXTURE;
let boardReads = 0;

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  MAddEditChannelFirmForm: { template: '<div data-testid="channel-form" />' },
  MMainDialog: { props: ['visible'], template: '<div v-if="visible" data-testid="dialog"><slot /></div>' },
};

function renderChannel(keys: string[]) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 7, last_name: 'Teste', name: 'Pessoa', role: 'Papel', role_slug: 'papel', username: 'pessoa' },
    keys
  );

  return renderWithPlugins(ChannelView, { global: { components: { Button, Skeleton }, stubs: STUBS }, pinia });
}

/** The text as read, with `Intl`'s non-breaking spaces ("R$\u00a01.575") as plain ones. */
function plain(node: Element | null | undefined): string {
  return (node?.textContent ?? '').replace(/\s+/g, ' ');
}

function rowOf(id: number) {
  return screen.getAllByTestId('channel-firm').find((row) => row.dataset.id === String(id))!;
}

function countOf(stage: string) {
  return screen.getAllByTestId('channel-stage').find((item) => item.dataset.stage === stage)?.dataset.count;
}

beforeEach(() => {
  advanced.length = 0;
  boardReads = 0;
  boardAnswer = OPERATOR_BOARD_FIXTURE;
  server.use(
    http.get('http://localhost/channel', () => {
      boardReads += 1;

      return HttpResponse.json({ data: structuredClone(boardAnswer) });
    }),
    http.post('http://localhost/channel/firms/:id/advance', async ({ params, request }) => {
      advanced.push({ id: Number(params.id), ...((await request.json()) as object) });

      return HttpResponse.json({ data: structuredClone(ADVANCED_BOARD_FIXTURE) });
    })
  );
});

afterEach(() => {
  i18n.global.locale.value = 'en';
});

describe('ChannelView — the operator (R8)', () => {
  it("shows Franco's share a month and a year, what is still to come, and the count per stage (A7)", async () => {
    renderChannel(OPERATOR_KEYS);

    const total = await screen.findByTestId('channel-total');

    expect(plain(total)).toContain('R$ 1.575,00/month');
    expect(total.dataset.value).toBe('157500');
    expect(plain(screen.getByTestId('channel-hero'))).toContain('3 active firms · R$ 18.900,00 a year');
    expect(plain(screen.getByTestId('channel-hero'))).toContain('R$ 1.575,00/month more when the others go live.');
    expect(plain(screen.getByTestId('channel-hero'))).toContain('Amount to be checked — no automatic billing');
    expect(['contato', 'demo', 'implantacao', 'ativo'].map(countOf)).toEqual(['1', '1', '1', '3']);
  });

  it("lists every firm with its stepper, the platform's fee and the share or 'once active'", async () => {
    renderChannel(OPERATOR_KEYS);

    await waitFor(() => expect(screen.getAllByTestId('channel-firm')).toHaveLength(6));

    const serraAzul = rowOf(1);
    const litoral = rowOf(3);

    expect(plain(serraAzul)).toContain('Contábil Serra Azul (exemplo)');
    expect(plain(serraAzul)).toContain('R$ 3.500,00/month');
    expect(plain(within(serraAzul).getByTestId('channel-share'))).toContain('R$ 525,00/month');
    expect(plain(within(serraAzul).getByTestId('channel-active'))).toContain('Active');
    expect(within(serraAzul).queryByTestId('channel-advance')).toBeNull();
    expect(plain(within(litoral).getByTestId('channel-share'))).toContain('once active');
    expect(within(litoral).getByRole('list', { name: 'Stage: Onboarding' })).toBeTruthy();
    expect(plain(within(litoral).getByTestId('channel-advance'))).toContain('Move to Active');
    expect(plain(screen.getByTestId('channel-lede'))).toContain('R$ 3.500,00 a month for the platform');
    expect(screen.getByTestId('channel-add')).toBeTruthy();
    expect(screen.getAllByTestId('channel-edit')).toHaveLength(6);
  });

  it('advances a firm naming the stage it showed, and the hero and the counts follow (C3, W41)', async () => {
    renderChannel(OPERATOR_KEYS);

    await fireEvent.click(within(await waitFor(() => rowOf(3))).getByTestId('channel-advance'));

    await waitFor(() => expect(plain(screen.getByTestId('channel-total'))).toContain('R$ 2.100,00/month'));
    expect(advanced).toEqual([{ id: 3, to: 'ativo' }]);
    expect(rowOf(3).dataset.stage).toBe('ativo');
    expect(within(rowOf(3)).getByTestId('channel-active')).toBeTruthy();
    expect(['implantacao', 'ativo'].map(countOf)).toEqual(['0', '4']);
    expect(boardReads).toBe(1);
  });

  it('reloads the board when the move was stale (422 invalid_transition), instead of showing a sentence', async () => {
    server.use(
      http.post('http://localhost/channel/firms/:id/advance', () =>
        HttpResponse.json(
          { code: 'invalid_transition', data: null, error: 'channel.invalidTransition', status_code: 422 },
          { status: 422 }
        )
      )
    );
    renderChannel(OPERATOR_KEYS);

    await fireEvent.click(within(await waitFor(() => rowOf(3))).getByTestId('channel-advance'));

    await waitFor(() => expect(boardReads).toBe(2));
  });

  it('opens the form to add a firm and to edit one', async () => {
    renderChannel(OPERATOR_KEYS);

    await fireEvent.click(await screen.findByTestId('channel-add'));

    expect(within(screen.getByTestId('dialog')).getByTestId('channel-form')).toBeTruthy();

    await fireEvent.click(within(rowOf(2)).getByTestId('channel-edit'));

    expect(within(screen.getByTestId('dialog')).getByTestId('channel-form')).toBeTruthy();
  });

  it('says the channel did not load and offers to try again', async () => {
    server.use(http.get('http://localhost/channel', () => HttpResponse.json({ error: 'boom' }, { status: 500 })));
    vi.spyOn(console, 'error').mockImplementation(() => {});

    renderChannel(OPERATOR_KEYS);

    expect(plain(await screen.findByRole('alert'))).toContain('The channel could not be loaded.');
  });
});

describe('ChannelView — Reinaldo reads it (A9, R9, AM15)', () => {
  beforeEach(() => {
    boardAnswer = CEO_BOARD_FIXTURE;
  });

  it('A9: renders his share, the stage and the year total, without any add, edit or advance control', async () => {
    i18n.global.locale.value = 'pt-BR';
    const { container } = renderChannel(CEO_KEYS);

    await waitFor(() => expect(screen.getAllByTestId('channel-firm')).toHaveLength(6));

    expect(plain(screen.getByTestId('channel-total'))).toContain('R$ 1.575,00/mês');
    expect(plain(screen.getByTestId('channel-hero'))).toContain('R$ 18.900,00 por ano');
    expect(plain(within(rowOf(1)).getByTestId('channel-share'))).toContain('R$ 525,00/mês');
    expect(within(rowOf(3)).getByRole('list', { name: 'Etapa: Implantação' })).toBeTruthy();
    expect(screen.queryByTestId('channel-add')).toBeNull();
    expect(screen.queryAllByTestId('channel-edit')).toHaveLength(0);
    expect(screen.queryAllByTestId('channel-advance')).toHaveLength(0);
    expect(container.querySelectorAll('button')).toHaveLength(0);
  });

  it('A9: shows no platform price and no "pela plataforma" lede, in either language', async () => {
    i18n.global.locale.value = 'pt-BR';
    const portuguese = renderChannel(CEO_KEYS);

    await waitFor(() => expect(screen.getAllByTestId('channel-firm')).toHaveLength(6));

    expect(plain(portuguese.container)).not.toContain('R$ 3.500');
    expect(plain(portuguese.container)).not.toContain('pela plataforma');
    expect(plain(portuguese.container)).not.toContain('15%');
    portuguese.unmount();

    i18n.global.locale.value = 'en';
    const english = renderChannel(CEO_KEYS);

    await waitFor(() => expect(screen.getAllByTestId('channel-firm')).toHaveLength(6));

    expect(plain(english.container)).not.toContain('R$ 3.500');
    expect(plain(english.container)).not.toContain('for the platform');
  });
});

describe('ChannelView — neither screen names the platform owners (A9)', () => {
  it.each([
    ['the operator', OPERATOR_KEYS, OPERATOR_BOARD_FIXTURE],
    ['Reinaldo', CEO_KEYS, CEO_BOARD_FIXTURE],
  ])("A9: %s's screen renders no Willy, APW, Battoni or split of the platform's share", async (_who, keys, board) => {
    boardAnswer = board;

    for (const locale of ['pt-BR', 'en'] as const) {
      i18n.global.locale.value = locale;
      const { container, unmount } = renderChannel(keys);

      await waitFor(() => expect(screen.getAllByTestId('channel-firm')).toHaveLength(6));

      OWNER_WORDS.forEach((word) => expect(plain(container)).not.toContain(word));
      unmount();
    }
  });
});
