import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import Textarea from 'primevue/textarea';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { MSlaChip, SLA_STATE } from '@PortalModule';
import { MATCHES_FIXTURE, REFERRAL_FIXTURE } from '../../../test/fixtures';
import MReferralForm from '../MReferralForm/MReferralForm.vue';
import MLeadRouting from './MLeadRouting.vue';

/*
 * The routing panel on the lead detail (rnc-1 F6, R22, A12): each partner with its score and
 * specialty, "Encaminhar" with a message — and, without consent, every button disabled with the
 * reason beside it. rnc-2 F3 (R10, A8): a referred partner shows where it took the referral — its
 * status, the SLA chip and, once closed, the contract value.
 */
const sent: Record<string, unknown>[] = [];

function renderRouting(canRefer = true) {
  return renderWithPlugins(MLeadRouting, {
    global: {
      components: { Button, MReferralForm, MSlaChip, Skeleton, Textarea },
      stubs: { AFormError: { props: ['error'], template: '<p v-if="error" data-testid="form-error">{{ error }}</p>' } },
    },
    props: { canRefer, company: 'Consultoria Prisma', leadId: 7 },
  });
}

beforeEach(() => {
  sent.length = 0;
  server.use(
    http.get('http://localhost/leads/7/partner-matches', () =>
      HttpResponse.json({ data: structuredClone(MATCHES_FIXTURE) })
    ),
    http.post('http://localhost/leads/7/referrals', async ({ request }) => {
      sent.push((await request.json()) as Record<string, unknown>);

      return HttpResponse.json(
        {
          data: {
            event: {
              at: '2026-11-19T11:00:00-03:00',
              data: { partner: 'Margem Certa (exemplo)', score: 92 },
              id: 90,
              type: 'encaminhamento.enviado',
              user: { id: 3, name: 'Patrícia Gomes' },
            },
            referral: REFERRAL_FIXTURE,
          },
        },
        { status: 201 }
      );
    })
  );
});

describe('MLeadRouting', () => {
  it('shows each partner with its score and specialty, best first', async () => {
    renderRouting();

    const rows = await screen.findAllByTestId('partner-match');

    expect(rows).toHaveLength(2);
    expect(within(rows[0]!).getByTestId('match-score').textContent).toContain('92%');
    expect(within(rows[0]!).getByTestId('match-score').textContent).toContain('Pricing and controllership');
    expect(within(rows[0]!).getByText('Regime de 30% para serviços')).toBeTruthy();
    expect(within(rows[1]!).getByTestId('match-referred')).toBeTruthy();
  });

  it('refers with the message and marks the partner as referred', async () => {
    const { emitted } = renderRouting();

    const rows = await screen.findAllByTestId('partner-match');

    await fireEvent.click(within(rows[0]!).getByTestId('refer-open'));

    const message = screen.getByTestId('referral-message') as HTMLTextAreaElement;

    expect(message.value).toContain('Consultoria Prisma');

    await fireEvent.update(message, 'Podem ligar amanhã?');
    await fireEvent.submit(screen.getByTestId('referral-form'));

    await waitFor(() => expect(sent).toEqual([{ message: 'Podem ligar amanhã?', partner_id: 7 }]));
    await waitFor(() =>
      expect(within(screen.getAllByTestId('partner-match')[0]!).getByTestId('match-referred')).toBeTruthy()
    );
    expect((emitted().onReferred as unknown[][])[0]![0]).toMatchObject({ type: 'encaminhamento.enviado' });
  });

  it('disables every button and says why when the lead did not consent', async () => {
    server.use(
      http.get('http://localhost/leads/7/partner-matches', () =>
        HttpResponse.json({
          data: { ...structuredClone(MATCHES_FIXTURE), blocked_reason: 'consent_refused', can_refer: false },
        })
      )
    );
    renderRouting();

    const reason = await screen.findByTestId('routing-blocked');
    const button = within(screen.getAllByTestId('partner-match')[0]!).getByTestId('refer-open') as HTMLButtonElement;

    expect(reason.textContent).toContain('did not agree');
    expect(button.disabled).toBe(true);
    expect(button.getAttribute('aria-describedby')).toBe(reason.id);
  });

  it('shows the API refusal inline when the consent is missing on submit', async () => {
    server.use(
      http.post('http://localhost/leads/7/referrals', () =>
        HttpResponse.json({ code: 'consent_required', error: 'partners.routing.consentRequired' }, { status: 422 })
      )
    );
    renderRouting();

    const rows = await screen.findAllByTestId('partner-match');

    await fireEvent.click(within(rows[0]!).getByTestId('refer-open'));
    await fireEvent.submit(screen.getByTestId('referral-form'));

    expect((await screen.findByTestId('form-error')).textContent).toContain('consent');
  });

  it('offers no referral to a person without leads.update', async () => {
    renderRouting(false);

    const rows = await screen.findAllByTestId('partner-match');

    expect(within(rows[0]!).queryByTestId('refer-open')).toBeNull();
  });

  it("shows each referral's status and SLA, and the contract value once closed (A8, R10)", async () => {
    const answer = structuredClone(MATCHES_FIXTURE);

    answer.matches[0]!.referral = {
      at: '2026-11-13T16:00:00-03:00',
      deal_value_cents: 1_200_000,
      id: 41,
      sla: { hours: 3, state: SLA_STATE.CUMPRIDO },
      status: 'fechado',
      status_at: '2026-11-18T15:00:00+00:00',
    };
    server.use(http.get('http://localhost/leads/7/partner-matches', () => HttpResponse.json({ data: answer })));
    renderRouting();

    const rows = await screen.findAllByTestId('partner-match');
    const closed = within(rows[0]!).getByTestId('match-referred');
    const sent = within(rows[1]!).getByTestId('match-referred');

    expect(closed.textContent).toMatch(/Closed · R\$\s12\.000,00/);
    expect(within(rows[0]!).getByTestId('sla-chip').dataset.sla).toBe('cumprido');
    expect(sent.textContent).toContain('Referred on 18/11');
    expect(within(rows[1]!).getByTestId('sla-chip').dataset.sla).toBe('ok');
    expect(within(rows[1]!).getByTestId('match-status').textContent).toContain('Sent');
  });
});
