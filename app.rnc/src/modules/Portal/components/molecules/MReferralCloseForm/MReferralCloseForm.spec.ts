import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import { beforeEach, describe, expect, it } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { REFERRAL_STATUS } from '../../../enums';
import { referralAt } from '../../../test/fixtures';
import MReferralCloseForm from './MReferralCloseForm.vue';

/*
 * "Fechar contrato" (rnc-2 R7): the first-year contract value in reais, above zero, sent in whole
 * cents with the move to `fechado`. The form owns the call and shows the API's refusal inline.
 */
const sent: Record<string, unknown>[] = [];
let answer: () => Response;

function renderForm() {
  return renderWithPlugins(MReferralCloseForm, {
    global: {
      components: { Button },
      // PrimeVue's InputNumber formats keystrokes jsdom cannot type: a native one with the same contract.
      stubs: {
        InputNumber: {
          emits: ['update:modelValue'],
          props: ['modelValue', 'inputId'],
          template:
            '<input data-testid="deal-value" type="number" :id="inputId" @input="$emit(\'update:modelValue\', $event.target.value === \'\' ? null : Number($event.target.value))" />',
        },
      },
    },
    props: { referral: referralAt(REFERRAL_STATUS.PROPOSTA) },
  });
}

beforeEach(() => {
  sent.length = 0;
  answer = () => HttpResponse.json({ data: referralAt(REFERRAL_STATUS.FECHADO, { deal_value_cents: 1_234_567 }) });
  server.use(
    http.post('http://localhost/portal/referrals/31/transition', async ({ request }) => {
      sent.push((await request.json()) as Record<string, unknown>);

      return answer();
    })
  );
});

describe('MReferralCloseForm', () => {
  it('closes with the value in whole cents', async () => {
    const { emitted } = renderForm();

    await fireEvent.update(screen.getByTestId('deal-value'), '12345.67');
    await fireEvent.submit(screen.getByTestId('close-form'));

    await waitFor(() => expect(sent).toEqual([{ deal_value_cents: 1_234_567, to: 'fechado' }]));
    await waitFor(() => expect((emitted().onSuccess as unknown[][])[0]![0]).toMatchObject({ status: 'fechado' }));
  });

  it('asks for a value above zero before calling', async () => {
    renderForm();

    await fireEvent.submit(screen.getByTestId('close-form'));
    expect(
      (await screen.findByText(/first-year contract value/i, { selector: '.a-form-error-stub' })).textContent
    ).toBeTruthy();

    await fireEvent.update(screen.getByTestId('deal-value'), '0');
    await fireEvent.submit(screen.getByTestId('close-form'));
    expect(sent).toEqual([]);
  });

  it("shows the API's refusal by its code", async () => {
    answer = () =>
      HttpResponse.json({ code: 'invalid_transition', message: 'referral.invalidTransition' }, { status: 422 });
    renderForm();

    await fireEvent.update(screen.getByTestId('deal-value'), '12000');
    await fireEvent.submit(screen.getByTestId('close-form'));

    expect((await screen.findByText(/moved on/i)).textContent).toBeTruthy();
  });
});
