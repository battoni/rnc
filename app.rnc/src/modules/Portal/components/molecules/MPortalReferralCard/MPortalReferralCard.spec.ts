import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { PortalReferral } from '../../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { REFERRAL_STATUS } from '../../../enums';
import {
  ACCEPTED_REFERRAL_FIXTURE,
  CONTRACT_REFERRAL_FIXTURE,
  NEW_REFERRAL_FIXTURE,
  referralAt,
} from '../../../test/fixtures';
import MSlaChip from '../MSlaChip/MSlaChip.vue';
import MPortalReferralCard from './MPortalReferralCard.vue';

/*
 * A referral in the partner's inbox (rnc-2 R6–R8, the demo's `referralCardView`): the company, its
 * profile, the items the partner's specialty covers, the score, Franco's message, the SLA chip — the
 * lead's contact only from the accept on — and the actions its status allows. A move without a
 * form is the card's own call; the two that need input (the meeting, the contract value) are asked
 * of the inbox, which opens the form. A refusal is the API's message, in a warn toast.
 */
const toastAdd = vi.fn();
const sent: Record<string, unknown>[] = [];
let answer: () => Response;

vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));

function renderCard(referral: PortalReferral) {
  return renderWithPlugins(MPortalReferralCard, {
    global: { components: { Button, MSlaChip } },
    props: { firmName: 'Franco Contabilidade', referral },
  });
}

function actionsShown(): string[] {
  return screen.queryAllByTestId('referral-action').map((button) => button.dataset.to ?? '');
}

beforeEach(() => {
  toastAdd.mockReset();
  sent.length = 0;
  answer = () => HttpResponse.json({ data: ACCEPTED_REFERRAL_FIXTURE });
  server.use(
    http.post('http://localhost/portal/referrals/31/transition', async ({ request }) => {
      sent.push((await request.json()) as Record<string, unknown>);

      return answer();
    })
  );
});

describe('MPortalReferralCard', () => {
  it.each([
    [REFERRAL_STATUS.ENVIADO, ['aceito', 'recusado']],
    [REFERRAL_STATUS.ACEITO, ['contato']],
    [REFERRAL_STATUS.CONTATO, ['reuniao']],
    [REFERRAL_STATUS.REUNIAO, ['proposta']],
    [REFERRAL_STATUS.PROPOSTA, ['fechado']],
    [REFERRAL_STATUS.FECHADO, []],
    [REFERRAL_STATUS.RECUSADO, []],
  ])('offers at %s only the moves the graph allows', (status, moves) => {
    renderCard(referralAt(status));

    expect(actionsShown()).toEqual(moves);
    expect(screen.getByTestId('portal-referral').dataset.status).toBe(status);
  });

  it('shows the profile, the covered items, the score, the message and the SLA', () => {
    renderCard(NEW_REFERRAL_FIXTURE);

    const card = screen.getByTestId('portal-referral');

    expect(card.textContent).toContain('Consultoria Prisma Ltda');
    expect(card.textContent).toContain('Services · Simples Nacional');
    expect(card.textContent).toContain('Novo layout da NF-e');
    expect(card.textContent).toContain('81% fit');
    expect(screen.getByTestId('referral-message').textContent).toContain('A Franco indica');
    expect(screen.getByTestId('sla-chip').dataset.sla).toBe('ok');
  });

  it("rnc-3 R21: shows a contract referral's summary — kind, end year, no clause and the value band, never the exact value", () => {
    renderCard(CONTRACT_REFERRAL_FIXTURE);

    const summary = screen.getByTestId('referral-contract');

    expect(summary.textContent).toContain('Distribution contract');
    expect(summary.textContent).toContain('until 2033');
    expect(summary.textContent).toContain('without the Reforma clause');
    expect(screen.getByTestId('referral-contract-range').getAttribute('data-min')).toBe('100000000');
    expect(screen.getByTestId('referral-contract-range').getAttribute('data-max')).toBe('200000000');
    expect(screen.getByTestId('referral-contract-range').textContent?.replace(/\s+/g, ' ')).toContain(
      'R$ 1 mi – R$ 2 mi a year'
    );
    expect(summary.textContent).not.toMatch(/\d{3}\.\d{3},\d{2}/);
  });

  it('rnc-3 R21: reads the top band as "from", and a plain referral shows no contract', () => {
    const { unmount } = renderCard({
      ...CONTRACT_REFERRAL_FIXTURE,
      contract: {
        ...CONTRACT_REFERRAL_FIXTURE.contract!,
        sem_clausula: false,
        value_range: { label: 'acima de R$ 2 mi', max_cents: null, min_cents: 200_000_000 },
      },
    });

    expect(screen.getByTestId('referral-contract-range').textContent?.replace(/\s+/g, ' ')).toContain(
      'from R$ 2 mi a year'
    );
    expect(screen.getByTestId('referral-contract').textContent).not.toContain('without the Reforma clause');
    unmount();

    renderCard(NEW_REFERRAL_FIXTURE);

    expect(screen.queryByTestId('referral-contract')).toBeNull();
  });

  it("hides the lead's contact before the accept and shows it after (A5)", () => {
    const { unmount } = renderCard(NEW_REFERRAL_FIXTURE);

    expect(screen.queryByTestId('referral-contact')).toBeNull();
    expect(screen.getByTestId('portal-referral').textContent).not.toContain('Ana Exemplo');
    unmount();

    renderCard(ACCEPTED_REFERRAL_FIXTURE);

    const contact = screen.getByTestId('referral-contact');

    expect(contact.textContent).toContain('Ana Exemplo');
    expect(contact.textContent).toContain('ana.prisma@exemplo.com.br');
    expect(contact.textContent).toContain('(11) 90000-0077');
  });

  it('names Franco as the way in to a portfolio client (R27)', () => {
    renderCard(
      referralAt(REFERRAL_STATUS.ACEITO, {
        contact: { email: 'contato@franco.exemplo', name: 'Franco Contabilidade', phone: '551130000000', via: 'firm' },
      })
    );

    expect(screen.getByTestId('referral-contact').textContent).toContain('Through Franco Contabilidade');
  });

  it("shows the firm's phone as a Brazilian number and dials it once, with a single +55", () => {
    renderCard(
      referralAt(REFERRAL_STATUS.ACEITO, {
        contact: { email: 'contato@franco.exemplo', name: 'Franco Contabilidade', phone: '551137325500', via: 'firm' },
      })
    );

    const link = screen.getByRole('link', { name: /3732-5500/ });

    expect(link.textContent).toContain('(11) 3732-5500');
    expect(link.getAttribute('href')).toBe('tel:+551137325500');
  });

  it('accepts through the API and hands the inbox the referral it answered', async () => {
    const { emitted } = renderCard(NEW_REFERRAL_FIXTURE);

    await fireEvent.click(screen.getByRole('button', { name: 'Accept' }));

    await waitFor(() => expect(sent).toEqual([{ to: 'aceito' }]));
    await waitFor(() => expect(emitted().onMoved).toBeTruthy());
    expect((emitted().onMoved as unknown[][])[0]![0]).toMatchObject({
      contact: { name: 'Ana Exemplo' },
      status: 'aceito',
    });
  });

  it('asks the inbox for the meeting form and for the contract form, calling nothing itself', async () => {
    const { emitted, unmount } = renderCard(referralAt(REFERRAL_STATUS.CONTATO));

    await fireEvent.click(screen.getByRole('button', { name: 'Book meeting' }));
    expect((emitted().onAskMeeting as unknown[][])[0]![0]).toMatchObject({ id: 31, status: 'contato' });
    unmount();

    const closing = renderCard(referralAt(REFERRAL_STATUS.PROPOSTA));

    await fireEvent.click(screen.getByRole('button', { name: 'Close contract' }));
    expect((closing.emitted().onAskDeal as unknown[][])[0]![0]).toMatchObject({ id: 31, status: 'proposta' });
    expect(sent).toEqual([]);
  });

  it('shows the API message when a move is refused, and asks the inbox to refresh', async () => {
    answer = () =>
      HttpResponse.json({ code: 'invalid_transition', message: 'referral.invalidTransition' }, { status: 422 });
    const { emitted } = renderCard(NEW_REFERRAL_FIXTURE);

    await fireEvent.click(screen.getByRole('button', { name: 'Decline' }));

    await waitFor(() => expect(toastAdd).toHaveBeenCalledTimes(1));
    expect(toastAdd.mock.calls[0]![0]).toMatchObject({
      severity: 'warn',
      summary: expect.stringContaining('moved on'),
    });
    expect(emitted().onStale).toHaveLength(1);
    expect(emitted().onMoved).toBeUndefined();
  });

  it('tells the meeting, the contract and the decline where they stand', () => {
    const { unmount } = renderCard(referralAt(REFERRAL_STATUS.REUNIAO, { meeting_at: '2026-11-19T17:00:00+00:00' }));

    expect(screen.getByTestId('referral-standing').textContent).toContain('19/11');
    expect(screen.getByTestId('referral-standing').textContent).toContain('14:00');
    unmount();

    const closed = renderCard(referralAt(REFERRAL_STATUS.FECHADO, { closed_at: '2026-11-18T15:00:00+00:00' }));

    expect(screen.getByTestId('referral-standing').textContent).toMatch(/R\$\s12\.000,00/);
    expect(screen.getByTestId('referral-standing').textContent).toContain('18/11/2026');
    closed.unmount();

    renderCard(referralAt(REFERRAL_STATUS.RECUSADO, { status_at: '2026-11-17T12:00:00+00:00' }));

    expect(screen.getByTestId('referral-standing').textContent).toContain(
      'Franco Contabilidade may refer another partner'
    );
  });
});
