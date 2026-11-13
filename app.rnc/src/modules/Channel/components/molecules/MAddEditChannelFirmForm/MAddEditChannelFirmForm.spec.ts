import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import { beforeEach, describe, expect, it } from 'vitest';
import type { ChannelFirm } from '../../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { ADVANCED_BOARD_FIXTURE, OPERATOR_BOARD_FIXTURE } from '../../../test/fixtures';
import MAddEditChannelFirmForm from './MAddEditChannelFirmForm.vue';

/*
 * The operator's add and edit of an RNC firm (rnc-3 R7, AM9): name, city, the platform's fee, the
 * share and the notes. A new firm starts on the defaults (`config/channel.php`) and always at contato;
 * the form says Franco's share as it is typed, refuses the bounds before the API does, and sends cents
 * and basis points.
 */
const DEFAULTS = OPERATOR_BOARD_FIXTURE.defaults!;
const LITORAL = OPERATOR_BOARD_FIXTURE.firms[2] as ChannelFirm;

const sent: { body: unknown; method: string; url: string }[] = [];

function renderForm(props: { defaults?: typeof DEFAULTS; firm?: ChannelFirm }) {
  return renderWithPlugins(MAddEditChannelFirmForm, {
    global: { components: { Button, InputNumber, InputText, Textarea } },
    props: { defaults: DEFAULTS, ...props },
  });
}

/** The text as read, with `Intl`'s non-breaking spaces ("R$\u00a01.575") as plain ones. */
function plain(node: Element | null | undefined): string {
  return (node?.textContent ?? '').replace(/\s+/g, ' ');
}

async function typeNumber(testId: string, value: string) {
  const input = screen.getByTestId(testId).querySelector('input') ?? (screen.getByTestId(testId) as HTMLInputElement);

  input.value = value;
  await fireEvent.blur(input);
}

beforeEach(() => {
  sent.length = 0;
  server.use(
    http.post('http://localhost/channel/firms', async ({ request }) => {
      sent.push({ body: await request.json(), method: 'POST', url: request.url });

      return HttpResponse.json({ data: ADVANCED_BOARD_FIXTURE }, { status: 201 });
    }),
    http.patch('http://localhost/channel/firms/:id', async ({ request }) => {
      sent.push({ body: await request.json(), method: 'PATCH', url: request.url });

      return HttpResponse.json({ data: ADVANCED_BOARD_FIXTURE });
    })
  );
});

describe('MAddEditChannelFirmForm', () => {
  it("starts a new firm on the defaults and says Franco's share at them", () => {
    renderForm({});

    expect((screen.getByTestId('channel-fee').querySelector('input') as HTMLInputElement).value).toContain('3.500,00');
    expect((screen.getByTestId('channel-share-rate').querySelector('input') as HTMLInputElement).value).toContain('15');
    expect(plain(screen.getByTestId('channel-share-preview'))).toContain('R$ 525,00/month');
    expect(plain(screen.getByTestId('channel-form'))).toContain('Starts at Contact');
  });

  it('follows the fee and the share as they are typed, rounding half-up as the API does (A7)', async () => {
    renderForm({});

    await typeNumber('channel-fee', '3.500,10');

    await waitFor(() => expect(plain(screen.getByTestId('channel-share-preview'))).toContain('R$ 525,02/month'));
  });

  it('adds a firm in cents and basis points, and hands the board to the view', async () => {
    const { emitted } = renderForm({});

    await fireEvent.update(screen.getByTestId('channel-name'), '  Ipê Contábil (exemplo) ');
    await fireEvent.update(screen.getByTestId('channel-city'), 'Campinas-SP');
    await typeNumber('channel-share-rate', '12,5');
    await fireEvent.click(screen.getByTestId('channel-save'));

    await waitFor(() => expect(emitted().onSuccess).toBeTruthy());
    expect(sent).toEqual([
      {
        body: {
          city: 'Campinas-SP',
          monthly_fee_cents: 350_000,
          name: 'Ipê Contábil (exemplo)',
          notes: null,
          share_bp: 1250,
        },
        method: 'POST',
        url: 'http://localhost/channel/firms',
      },
    ]);
    expect(emitted().onSuccess?.[0]).toEqual([ADVANCED_BOARD_FIXTURE, 'Ipê Contábil (exemplo)']);
  });

  it("edits a firm's fee, share and notes, never its stage", async () => {
    renderForm({ firm: LITORAL });

    expect((screen.getByTestId('channel-name') as HTMLInputElement).value).toBe(LITORAL.name);
    expect(plain(screen.getByTestId('channel-form'))).not.toContain('Starts at Contact');

    await typeNumber('channel-fee', '4.000,00');
    await fireEvent.update(screen.getByTestId('channel-notes'), 'Reunião com o sócio');
    await fireEvent.click(screen.getByTestId('channel-save'));

    await waitFor(() => expect(sent).toHaveLength(1));
    expect(sent[0]).toEqual({
      body: {
        city: 'Santos-SP',
        monthly_fee_cents: 400_000,
        name: LITORAL.name,
        notes: 'Reunião com o sócio',
        share_bp: 1500,
      },
      method: 'PATCH',
      url: 'http://localhost/channel/firms/3',
    });
  });

  it('refuses an empty name and city, a fee off R$ 0,01–100.000,00 and a share over 50 % before calling (AM9)', async () => {
    renderForm({});

    await typeNumber('channel-fee', '100.000,01');
    await typeNumber('channel-share-rate', '50,01');
    await fireEvent.click(screen.getByTestId('channel-save'));

    const form = screen.getByTestId('channel-form');

    expect(plain(form)).toContain('Required.');
    expect(plain(form)).toContain('Between R$ 0,01 and R$ 100.000,00.');
    expect(plain(form)).toContain('Between 0% and 50%.');
    expect(sent).toEqual([]);
  });

  it("names the API's refusal by field", async () => {
    server.use(
      http.post('http://localhost/channel/firms', () =>
        HttpResponse.json({ errors: { share_bp: ['validation.between'] }, message: 'invalid' }, { status: 422 })
      )
    );
    renderForm({});

    await fireEvent.update(screen.getByTestId('channel-name'), 'Ipê Contábil (exemplo)');
    await fireEvent.update(screen.getByTestId('channel-city'), 'Campinas-SP');
    await fireEvent.click(screen.getByTestId('channel-save'));

    await waitFor(() => expect(plain(screen.getByTestId('channel-form'))).toContain('Between 0% and 50%.'));
  });
});
