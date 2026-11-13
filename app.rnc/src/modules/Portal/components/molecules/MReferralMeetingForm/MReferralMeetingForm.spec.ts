import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { REFERRAL_STATUS } from '../../../enums';
import { referralAt } from '../../../test/fixtures';
import MReferralMeetingForm from './MReferralMeetingForm.vue';

/*
 * "Agendar reunião" (rnc-2 R7): the meeting's date and time, in the future, before the move to
 * `reuniao`. The form owns the call and shows the API's refusal inline; the inbox applies the answer.
 */
const sent: Record<string, unknown>[] = [];
let answer: () => Response;

function renderForm() {
  return renderWithPlugins(MReferralMeetingForm, {
    global: { components: { Button, InputText } },
    props: { referral: referralAt(REFERRAL_STATUS.CONTATO) },
  });
}

beforeEach(() => {
  vi.useFakeTimers({ now: new Date('2026-11-16T13:00:00Z'), shouldAdvanceTime: true });
  sent.length = 0;
  answer = () =>
    HttpResponse.json({ data: referralAt(REFERRAL_STATUS.REUNIAO, { meeting_at: '2026-11-19T17:00:00+00:00' }) });
  server.use(
    http.post('http://localhost/portal/referrals/31/transition', async ({ request }) => {
      sent.push((await request.json()) as Record<string, unknown>);

      return answer();
    })
  );
});

afterEach(() => vi.useRealTimers());

describe('MReferralMeetingForm', () => {
  it('moves the referral to reunião with the meeting as an instant', async () => {
    const { emitted } = renderForm();

    await fireEvent.update(screen.getByTestId('meeting-at'), '2026-11-19T14:00');
    await fireEvent.submit(screen.getByTestId('meeting-form'));

    await waitFor(() => expect(sent).toHaveLength(1));
    expect(sent[0]).toEqual({ meeting_at: new Date('2026-11-19T14:00').toISOString(), to: 'reuniao' });
    await waitFor(() => expect((emitted().onSuccess as unknown[][])[0]![0]).toMatchObject({ status: 'reuniao' }));
  });

  it('asks for a date and time, and for one in the future, before calling', async () => {
    renderForm();

    await fireEvent.submit(screen.getByTestId('meeting-form'));
    expect((await screen.findByText(/choose the meeting/i)).textContent).toBeTruthy();

    await fireEvent.update(screen.getByTestId('meeting-at'), '2026-11-10T09:00');
    await fireEvent.submit(screen.getByTestId('meeting-form'));
    expect((await screen.findByText(/in the future/i)).textContent).toBeTruthy();
    expect(sent).toEqual([]);
  });

  it("shows the API's refusal inline", async () => {
    answer = () =>
      HttpResponse.json({ errors: { meeting_at: ['portal.referrals.meetingInPast'] }, message: 'x' }, { status: 422 });
    renderForm();

    await fireEvent.update(screen.getByTestId('meeting-at'), '2026-11-19T14:00');
    await fireEvent.submit(screen.getByTestId('meeting-form'));

    expect((await screen.findByText(/in the future/i)).textContent).toBeTruthy();
  });
});
