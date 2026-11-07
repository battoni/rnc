import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Skeleton from 'primevue/skeleton';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { PortalReferral } from '../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import MPortalReferralCard from '../../components/molecules/MPortalReferralCard/MPortalReferralCard.vue';
import MReferralCloseForm from '../../components/molecules/MReferralCloseForm/MReferralCloseForm.vue';
import MReferralMeetingForm from '../../components/molecules/MReferralMeetingForm/MReferralMeetingForm.vue';
import MSlaChip from '../../components/molecules/MSlaChip/MSlaChip.vue';
import { REFERRAL_STATUS, SLA_STATE } from '../../enums';
import { PORTAL_ME_FIXTURE, referralAt } from '../../test/fixtures';
import PortalInboxView from './PortalInboxView.view.vue';

/*
 * The partner's inbox (rnc-2 F3, R3, R5–R8; the demo's `partnerInbox`): it names the partner the
 * API says is signed in and Franco's firm, counts Novos · Em andamento · Fechados · SLA estourado
 * from the list itself (W22), lists "Próximas reuniões", and every card's move lands in place —
 * the card a move answered is replaced, nothing is fetched again. A failed load offers a retry.
 */
const toastAdd = vi.fn();
let inbox: PortalReferral[];
let inboxAnswer: () => Response;
let inboxCalls = 0;

vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));

function renderView() {
  return renderWithPlugins(PortalInboxView, {
    global: {
      components: {
        Button,
        InputText,
        MPortalReferralCard,
        MReferralCloseForm,
        MReferralMeetingForm,
        MSlaChip,
        Skeleton,
      },
      stubs: {
        RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' },
        MMainDialog: {
          props: ['visible', 'title'],
          template: '<div v-if="visible" data-testid="dialog"><slot /></div>',
        },
      },
    },
  });
}

function kpi(name: string): string {
  return screen.getByTestId(`kpi-${name}`).textContent ?? '';
}

beforeEach(() => {
  vi.useFakeTimers({ now: new Date('2026-11-16T13:00:00Z'), shouldAdvanceTime: true });
  toastAdd.mockReset();
  inboxCalls = 0;
  inbox = [
    referralAt(REFERRAL_STATUS.ENVIADO, {
      company: 'Nova Ltda',
      id: 1,
      sla: { hours: 30, state: SLA_STATE.ESTOURADO },
    }),
    referralAt(REFERRAL_STATUS.ENVIADO, { company: 'Recente Ltda', id: 2 }),
    referralAt(REFERRAL_STATUS.CONTATO, { company: 'Em Contato Ltda', id: 3 }),
    referralAt(REFERRAL_STATUS.REUNIAO, { company: 'Depois Ltda', id: 4, meeting_at: '2026-11-25T13:00:00+00:00' }),
    referralAt(REFERRAL_STATUS.REUNIAO, { company: 'Antes Ltda', id: 5, meeting_at: '2026-11-18T17:30:00+00:00' }),
    referralAt(REFERRAL_STATUS.FECHADO, { company: 'Fechada Ltda', id: 6 }),
    referralAt(REFERRAL_STATUS.RECUSADO, { company: 'Recusada Ltda', id: 7 }),
  ];
  inboxAnswer = () => HttpResponse.json({ data: inbox });
  server.use(
    http.get('http://localhost/portal/me', () => HttpResponse.json({ data: PORTAL_ME_FIXTURE })),
    http.get('http://localhost/portal/referrals', () => {
      inboxCalls += 1;

      return inboxAnswer();
    }),
    http.post('http://localhost/portal/referrals/:id/transition', async ({ params, request }) => {
      const body = (await request.json()) as { meeting_at?: string; to: REFERRAL_STATUS };
      const current = inbox.find((row) => row.id === Number(params.id))!;

      return HttpResponse.json({
        data: referralAt(body.to, {
          ...current,
          contact: referralAt(body.to).contact,
          meeting_at: body.meeting_at ?? current.meeting_at,
          status: body.to,
        }),
      });
    })
  );
});

afterEach(() => vi.useRealTimers());

describe('PortalInboxView', () => {
  it("names the signed-in partner and Franco's firm", async () => {
    renderView();

    expect((await screen.findByTestId('portal-partner')).textContent).toContain('NexoERP (exemplo)');
    expect(screen.getByTestId('portal-home').textContent).toContain('Franco Contabilidade');
  });

  it("links to the month's statement, also where the rail shows no children (rnc-2 R11)", async () => {
    renderView();

    const link = await screen.findByTestId('portal-statement-link');

    expect(link.textContent).toContain("See this month's statement");
    expect(link.getAttribute('data-to')).toContain('portal-statement-en');
  });

  it('counts Novos · Em andamento · Fechados · SLA estourado from the list (R5)', async () => {
    renderView();

    await screen.findAllByTestId('portal-referral');

    expect(kpi('novos')).toContain('2');
    expect(kpi('andamento')).toContain('3');
    expect(kpi('fechados')).toContain('1');
    expect(kpi('estourado')).toContain('1');
  });

  it('lists the coming meetings, soonest first', async () => {
    renderView();

    const meetings = within(await screen.findByTestId('portal-meetings')).getAllByRole('listitem');

    expect(meetings.map((item) => item.textContent)).toEqual([
      expect.stringContaining('Antes Ltda'),
      expect.stringContaining('Depois Ltda'),
    ]);
    expect(meetings[0]!.textContent).toContain('14:30');
  });

  it('lists every referral in the order the API sent them', async () => {
    renderView();

    const cards = await screen.findAllByTestId('portal-referral');

    expect(cards.map((card) => card.dataset.referral)).toEqual(['1', '2', '3', '4', '5', '6', '7']);
  });

  it('replaces the card a move answered, and its count follows (granular)', async () => {
    renderView();

    const card = (await screen.findAllByTestId('portal-referral'))[1]!;

    await fireEvent.click(within(card).getByRole('button', { name: 'Accept' }));

    await waitFor(() => expect(screen.getAllByTestId('portal-referral')[1]!.dataset.status).toBe('aceito'));
    expect(kpi('novos')).toContain('1');
    expect(kpi('andamento')).toContain('4');
    expect(inboxCalls).toBe(1);
  });

  it('books a meeting through its form and lists it', async () => {
    renderView();

    const card = (await screen.findAllByTestId('portal-referral'))[2]!;

    await fireEvent.click(within(card).getByRole('button', { name: 'Book meeting' }));
    await fireEvent.update(within(await screen.findByTestId('dialog')).getByTestId('meeting-at'), '2026-11-17T09:00');
    await fireEvent.submit(screen.getByTestId('meeting-form'));

    await waitFor(() => expect(screen.queryByTestId('dialog')).toBeNull());
    expect(screen.getAllByTestId('portal-referral')[2]!.dataset.status).toBe('reuniao');
    expect(within(screen.getByTestId('portal-meetings')).getAllByRole('listitem')[0]!.textContent).toContain(
      'Em Contato Ltda'
    );
  });

  it('asks the API again when a card says it is stale', async () => {
    server.use(
      http.post('http://localhost/portal/referrals/:id/transition', () =>
        HttpResponse.json({ code: 'invalid_transition', message: 'referral.invalidTransition' }, { status: 422 })
      )
    );
    renderView();

    const card = (await screen.findAllByTestId('portal-referral'))[0]!;

    await fireEvent.click(within(card).getByRole('button', { name: 'Accept' }));

    await waitFor(() => expect(inboxCalls).toBe(2));
  });

  it('says so when Franco has referred nothing yet', async () => {
    inbox = [];
    renderView();

    expect((await screen.findByTestId('portal-empty')).textContent).toContain('Franco Contabilidade');
    expect(screen.getByTestId('portal-meetings').textContent).toMatch(/no meeting/i);
  });

  it('offers a retry when the inbox does not load', async () => {
    inboxAnswer = () => HttpResponse.json({ error: 'x' }, { status: 500 });
    renderView();

    const retry = await screen.findByRole('button', { name: /try again/i });

    inboxAnswer = () => HttpResponse.json({ data: inbox });
    await fireEvent.click(retry);

    expect(await screen.findAllByTestId('portal-referral')).toHaveLength(7);
  });
});
