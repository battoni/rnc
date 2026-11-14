import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { RadarSummary } from '../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import { MRadarTypeDetail, MRadarTypeRow } from '../../components';
import { CONTRATOS, CONTRATOS_DETAIL, MARGEM, MARGEM_DOIS, RADAR_FIXTURE } from '../../test/fixtures';
import RadarView from './RadarView.view.vue';

/*
 * "Radar da carteira" (rnc-2 F10, R24–R27, A21's Vitest half): the four KPIs, the opportunity rows
 * and apart the risk rows; each row's picker offers only the partners the API says cover its rule,
 * "Encaminhar os N maiores" with N the chosen partner's next batch, "N sem autorização ficam fora do
 * lote", the split payment's "retidos na liquidação" line. Sending posts the rule and the partner,
 * then reads the Radar again: once nothing is left for that partner the button says "Todos já
 * encaminhados" and is disabled.
 */
const posted: unknown[] = [];
const answers: { summary: RadarSummary } = { summary: RADAR_FIXTURE };
let summaryRequests = 0;

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  // PrimeVue's Select opens an overlay jsdom cannot drive: a native one with the same contract.
  Select: {
    emits: ['update:modelValue'],
    props: ['inputId', 'modelValue', 'options'],
    template:
      '<select :data-testid="inputId" :value="modelValue" @change="$emit(\'update:modelValue\', Number($event.target.value))"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
  },
};

/** The firm's opt-in modules this screen reads (rnc-4 R16): partners on, unless a test turns it off. */
const WITH_PARTNERS = ['portfolio', 'fiscal', 'radar', 'partners'];

function renderRadar(modules: string[] = WITH_PARTNERS) {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    {
      firm: { modules, name: 'Franco Contabilidade', slug: 'franco' },
      id: 2,
      last_name: 'Franco',
      name: 'Reinaldo',
      role: 'CEO',
      role_slug: 'gestao',
      username: 'reinaldo',
    } as never,
    ['leads.read', 'radar.manage']
  );

  return renderWithPlugins(RadarView, {
    global: { components: { Button, MRadarTypeDetail, MRadarTypeRow, Skeleton }, stubs: STUBS },
    pinia,
  });
}

function rowOf(rule: string): HTMLElement {
  return screen.getAllByTestId('radar-type').find((row) => row.getAttribute('data-rule') === rule)!;
}

/** The Radar after a batch to Margem Certa took everything left for it. */
function exhaustedForMargem(): RadarSummary {
  return {
    ...RADAR_FIXTURE,
    types: RADAR_FIXTURE.types.map((type) =>
      type.rule === CONTRATOS.rule ? { ...type, partners: [{ ...MARGEM, batch: 0, sent: 12 }, MARGEM_DOIS] } : type
    ),
  };
}

beforeEach(() => {
  posted.length = 0;
  summaryRequests = 0;
  answers.summary = RADAR_FIXTURE;
  server.use(
    http.get('http://localhost/radar', () => {
      summaryRequests++;

      return HttpResponse.json({ data: answers.summary });
    }),
    http.get('http://localhost/radar/types/contratos', () => HttpResponse.json({ data: CONTRATOS_DETAIL })),
    http.post('http://localhost/radar/referrals', async ({ request }) => {
      const body = (await request.json()) as { partner_id: number };

      posted.push(body);

      if (body.partner_id === 404) {
        return HttpResponse.json(
          { code: 'partner_not_covering', message: 'radar.partnerNotCovering' },
          { status: 422 }
        );
      }

      answers.summary = exhaustedForMargem();

      return HttpResponse.json({ data: { sent: 10, skipped_already: 0, skipped_no_consent: 2 } });
    })
  );
});

describe('RadarView', () => {
  it('R24: shows the four KPIs, the total as a range of opportunities only', async () => {
    renderRadar();

    const total = await screen.findByTestId('radar-total');

    expect(screen.getByTestId('radar-clients').getAttribute('data-value')).toBe('500');
    expect(screen.getByTestId('radar-with-opportunity').textContent).toContain('480');
    expect(total.getAttribute('data-low')).toBe('13000000');
    expect(total.getAttribute('data-high')).toBe('38000000');
    expect(screen.getByTestId('radar-risk-types').textContent).toContain('1');
  });

  it('R24: lists the opportunity rows, and the risks apart', async () => {
    renderRadar();

    const opportunities = await screen.findByTestId('radar-opportunities');

    expect(
      within(opportunities)
        .getAllByTestId('radar-type')
        .map((row) => row.getAttribute('data-rule'))
    ).toEqual(['contratos', 'servicos-30']);
    expect(within(screen.getByTestId('radar-risks')).getAllByTestId('radar-type')).toHaveLength(1);
    expect(within(rowOf('contratos')).getByTestId('radar-range').textContent).toMatch(/120\.000,00.*360\.000,00/);
    expect(within(rowOf('contratos')).getByTestId('radar-estimate')).toBeTruthy();
  });

  it('R26: the split-payment row says what is held back per month', async () => {
    renderRadar();

    await screen.findAllByTestId('radar-type');

    const held = within(rowOf('split-payment')).getByTestId('radar-split-held');

    expect(held.getAttribute('data-low')).toBe('1060000');
    expect(held.textContent).toContain('held back when B2B sales settle (estimate)');
    expect(rowOf('split-payment').textContent).toContain('a month');
  });

  it("R27: the picker offers only the API's covering partners, and the button counts the chosen one's batch", async () => {
    renderRadar();

    await screen.findAllByTestId('radar-type');

    const row = rowOf('contratos');
    const picker = within(row).getByTestId('radar-partner-contratos') as HTMLSelectElement;

    expect(Array.from(picker.options).map((option) => option.value)).toEqual([
      String(MARGEM.id),
      String(MARGEM_DOIS.id),
    ]);
    expect(within(row).getByTestId('radar-refer').textContent).toContain('Send the 10 largest');
    expect(within(row).queryByTestId('radar-sent')).toBeNull();

    await fireEvent.update(picker, String(MARGEM_DOIS.id));

    expect(within(row).getByTestId('radar-refer').textContent).toContain('Send the 4 largest');
    expect(within(row).getByTestId('radar-sent').textContent).toContain('6 sent');
  });

  it('R27: says how many stay out for lack of consent, and a row no specialty covers', async () => {
    renderRadar();

    await screen.findAllByTestId('radar-type');

    expect(within(rowOf('contratos')).getByTestId('radar-no-consent').textContent).toContain(
      '2 without consent stay out of the batch'
    );
    expect(within(rowOf('servicos-30')).getByTestId('radar-no-partner')).toBeTruthy();
    expect(within(rowOf('servicos-30')).queryByTestId('radar-refer')).toBeNull();
  });

  it('A21: sends the batch, reads the Radar again, and the button then says "Todos já encaminhados"', async () => {
    renderRadar();

    await screen.findAllByTestId('radar-type');
    await fireEvent.click(within(rowOf('contratos')).getByTestId('radar-refer'));

    await waitFor(() =>
      expect(within(rowOf('contratos')).getByTestId('radar-refer').textContent).toContain('All already sent')
    );

    expect(posted).toEqual([{ partner_id: MARGEM.id, rule: 'contratos' }]);
    expect(summaryRequests).toBe(2);
    expect(within(rowOf('contratos')).getByTestId('radar-refer').hasAttribute('disabled')).toBe(true);
    expect(within(rowOf('contratos')).getByTestId('radar-sent').textContent).toContain('12 sent');
  });

  it('says a refusal the API names by code on the row', async () => {
    answers.summary = {
      ...RADAR_FIXTURE,
      types: [{ ...CONTRATOS, partners: [{ ...MARGEM, id: 404 }] }],
    };
    renderRadar();

    await screen.findAllByTestId('radar-type');
    await fireEvent.click(within(rowOf('contratos')).getByTestId('radar-refer'));

    const alert = await within(rowOf('contratos')).findByRole('alert');

    expect(alert.textContent).toContain("This partner's specialty does not cover this item.");
    expect(summaryRequests).toBe(1);
  });

  it("R25: opens a row's clients with their proof", async () => {
    renderRadar();

    await screen.findAllByTestId('radar-type');
    await fireEvent.click(within(rowOf('contratos')).getByTestId('radar-open'));

    const detail = await within(rowOf('contratos')).findByTestId('radar-detail');

    await waitFor(() => expect(within(detail).getAllByTestId('radar-client')).toHaveLength(2));
    expect(
      within(detail)
        .getAllByTestId('radar-proof')
        .map((proof) => proof.textContent?.trim())
    ).toContain('from the client record');
  });
  /*
   * rnc-4 R19: without `partners` the API answers every row's picker empty — and the screen must not
   * read that as "no partner covers this" (an invitation to register one): the batch column is not
   * drawn at all. The rows, their ranges and the clients' proof stay.
   */
  it('rnc-4 R19: without `partners` no row offers a batch, nor says a partner is missing', async () => {
    answers.summary = { ...RADAR_FIXTURE, types: RADAR_FIXTURE.types.map((type) => ({ ...type, partners: [] })) };
    renderRadar(['portfolio', 'fiscal', 'radar']);

    await screen.findAllByTestId('radar-type');

    expect(screen.queryAllByTestId('radar-refer')).toHaveLength(0);
    expect(screen.queryAllByTestId('radar-no-partner')).toHaveLength(0);
    expect(screen.queryAllByTestId('radar-no-consent')).toHaveLength(0);
    expect(screen.getAllByTestId('radar-range').length).toBe(RADAR_FIXTURE.types.length);
    expect(screen.getAllByTestId('radar-open').length).toBe(RADAR_FIXTURE.types.length);
  });
});
