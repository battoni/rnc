import { fireEvent, screen, waitFor, within } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { ContractAlert, LegalPartner } from '../../interfaces';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import { MContractRow } from '../../components';
import { ANDRADE, BANDEIRA, CONTRACTS_FIXTURE, MARGEM, REFUSED, UNANSWERED, WITH_ANDRADE } from '../../test/fixtures';
import ContractsView from './ContractsView.view.vue';

/*
 * "Alerta de contratos" (rnc-3 F7, R19–R22 UI, completes A20): the dated note, the four KPIs, the
 * at-risk rows most exposed first — each with "<tipo> · desde <início> · <valor>/ano · até <fim>",
 * the '27–'33 chips lit on the years it is exposed and the exposure bar (never under 2 %) —, the
 * legal partner picker and "Encaminhar ao jurídico" (disabled with the reason when the client has
 * not consented), "Com <parceiro>" once sent, "Sem parceiro jurídico na rede." without one, and
 * "Cláusula incluída", after which the row leaves the list (the API's list, read again).
 */
const toastAdd = vi.fn();
const posted: { body: unknown; path: string }[] = [];
const answers: { alert: ContractAlert; partners: LegalPartner[] } = {
  alert: CONTRACTS_FIXTURE,
  partners: [ANDRADE, MARGEM],
};
let alertRequests = 0;
let alertFails = false;

vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  MMainDialog: {
    props: ['title', 'visible'],
    template: '<div v-if="visible" data-testid="dialog" :data-title="title"><slot /></div>',
  },
  MImportContractsForm: { template: '<form data-testid="import-form" />' },
  // PrimeVue's Select opens an overlay jsdom cannot drive: a native one with the same contract.
  Select: {
    emits: ['update:modelValue'],
    props: ['inputId', 'modelValue', 'options'],
    template:
      '<select :data-testid="inputId" :value="modelValue" @change="$emit(\'update:modelValue\', Number($event.target.value))"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option></select>',
  },
};

function renderContracts() {
  const pinia = createPinia();
  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { id: 2, last_name: 'Franco', name: 'Reinaldo', role: 'CEO', role_slug: 'gestao', username: 'reinaldo' },
    ['leads.read', 'contracts.manage']
  );

  return renderWithPlugins(ContractsView, {
    global: { components: { Button, MContractRow, Skeleton }, stubs: STUBS },
    pinia,
  });
}

function rowOf(id: number): HTMLElement {
  return screen.getAllByTestId('contract').find((row) => row.getAttribute('data-id') === String(id))!;
}

function idsShown(): string[] {
  return screen.queryAllByTestId('contract').map((row) => row.getAttribute('data-id') ?? '');
}

function plain(text: string | null | undefined): string {
  return (text ?? '').replace(/\s+/g, ' ').trim();
}

beforeEach(() => {
  toastAdd.mockReset();
  posted.length = 0;
  alertRequests = 0;
  alertFails = false;
  answers.alert = CONTRACTS_FIXTURE;
  answers.partners = [ANDRADE, MARGEM];
  server.use(
    http.get('http://localhost/contracts', () => {
      alertRequests++;

      return alertFails
        ? HttpResponse.json({ message: 'boom' }, { status: 500 })
        : HttpResponse.json({ data: answers.alert });
    }),
    http.get('http://localhost/contracts/legal-partners', () => HttpResponse.json({ data: answers.partners })),
    http.post('http://localhost/contracts/:id/referrals', async ({ params, request }) => {
      const body = (await request.json()) as { partner_id: number };

      posted.push({ body, path: `contracts/${String(params.id)}/referrals` });

      if (body.partner_id === 409) {
        return HttpResponse.json(
          { code: 'already_referred', message: 'partners.routing.alreadyReferred' },
          { status: 409 }
        );
      }

      const partner = answers.partners.find((candidate) => candidate.id === body.partner_id)!;

      answers.alert = {
        ...answers.alert,
        contracts: answers.alert.contracts.map((row) =>
          row.id === Number(params.id) ? { ...row, referred_to: [{ id: partner.id, name: partner.name }] } : row
        ),
        summary: { ...answers.alert.summary, referred: answers.alert.summary.referred + 1 },
      };

      return HttpResponse.json({ data: { id: 501 } }, { status: 201 });
    }),
    http.post('http://localhost/contracts/:id/clause', ({ params }) => {
      posted.push({ body: null, path: `contracts/${String(params.id)}/clause` });
      answers.alert = {
        ...answers.alert,
        contracts: answers.alert.contracts.filter((row) => row.id !== Number(params.id)),
        summary: { ...answers.alert.summary, at_risk: answers.alert.summary.at_risk - 1 },
      };

      return HttpResponse.json({ data: { has_reforma_clause: true, id: Number(params.id) } });
    })
  );
});

describe('ContractsView', () => {
  it('R19: carries the dated statement in its note', async () => {
    renderContracts();

    await screen.findAllByTestId('contract');

    const note = screen.getByTestId('contracts-note');

    expect(note.textContent).toContain('2027 to 2033');
    expect(note.textContent).toContain('read on 05/11/2026');
  });

  it('R19: shows the four KPIs — at risk of all, the annual value, value × years exposed and those sent to legal', async () => {
    renderContracts();

    const atRisk = await screen.findByTestId('contracts-at-risk');

    expect(plain(atRisk.textContent)).toContain('4 of 40');
    expect(screen.getByTestId('contracts-value').getAttribute('data-value')).toBe('155000000');
    expect(plain(screen.getByTestId('contracts-value').textContent)).toContain('R$ 1.550.000,00');
    expect(screen.getByTestId('contracts-exposure').getAttribute('data-value')).toBe('875000000');
    expect(screen.getByTestId('contracts-referred').textContent).toContain('1');
  });

  it("R19: lists the API's rows in order, each with its sentence", async () => {
    renderContracts();

    await screen.findAllByTestId('contract');

    expect(idsShown()).toEqual(['9', '12', '18', '15']);
    expect(plain(rowOf(BANDEIRA.id).textContent)).toContain('Centro Médico Bandeira Ltda');
    expect(plain(within(rowOf(BANDEIRA.id)).getByTestId('contract-line').textContent)).toBe(
      'Distribution · since 2024 · R$ 1.090.000,00/year · until 2033'
    );
  });

  it("R19: lights the '27–'33 chips only on the years the contract is exposed", async () => {
    renderContracts();

    await screen.findAllByTestId('contract');

    const litOf = (id: number) =>
      within(rowOf(id))
        .getAllByTestId('contract-year')
        .filter((chip) => chip.getAttribute('data-lit') === 'true')
        .map((chip) => chip.textContent?.trim());

    expect(
      within(rowOf(BANDEIRA.id))
        .getAllByTestId('contract-year')
        .map((chip) => chip.textContent?.trim())
    ).toEqual(["'27", "'28", "'29", "'30", "'31", "'32", "'33"]);
    expect(litOf(BANDEIRA.id)).toHaveLength(7);
    expect(litOf(REFUSED.id)).toEqual(["'28", "'29"]);
    expect(litOf(UNANSWERED.id)).toEqual(["'27"]);
  });

  it('R19: draws the exposure bar against the most exposed row, never under 2 %', async () => {
    renderContracts();

    await screen.findAllByTestId('contract');

    const widthOf = (id: number) => (within(rowOf(id)).getByTestId('contract-bar') as HTMLElement).style.width;

    expect(widthOf(BANDEIRA.id)).toBe('100%');
    expect(widthOf(WITH_ANDRADE.id)).toBe('6.3%');
    expect(widthOf(UNANSWERED.id)).toBe('2%');
  });

  it('A20: a row without consent has the button disabled, with the reason, and no picker', async () => {
    renderContracts();

    await screen.findAllByTestId('contract');

    const refused = rowOf(REFUSED.id);
    const unanswered = rowOf(UNANSWERED.id);

    expect(within(refused).getByTestId('contract-refer').hasAttribute('disabled')).toBe(true);
    expect(within(refused).getByTestId('contract-consent-blocked').textContent).toContain(
      'Client without consent in the services contract'
    );
    expect(within(refused).queryByTestId(`contract-partner-${REFUSED.id}`)).toBeNull();
    expect(within(unanswered).getByTestId('contract-refer').hasAttribute('disabled')).toBe(true);
    expect(within(unanswered).getByTestId('contract-consent-blocked').textContent).toContain('No consent recorded');
    expect(within(rowOf(BANDEIRA.id)).getByTestId('contract-refer').hasAttribute('disabled')).toBe(false);
  });

  it('A20: the picker offers the legal partners in the API order', async () => {
    renderContracts();

    await screen.findAllByTestId('contract');

    const picker = within(rowOf(BANDEIRA.id)).getByTestId(`contract-partner-${BANDEIRA.id}`) as HTMLSelectElement;

    expect(Array.from(picker.options).map((option) => option.textContent)).toEqual([
      'Andrade Tributário (exemplo) · Tax law',
      'Margem Certa (exemplo) · Pricing and controllership',
    ]);
  });

  it('A20: sending to the chosen partner reads the alert again, and the row says "Com <parceiro>"', async () => {
    renderContracts();

    await screen.findAllByTestId('contract');

    const row = rowOf(BANDEIRA.id);

    await fireEvent.update(within(row).getByTestId(`contract-partner-${BANDEIRA.id}`), String(MARGEM.id));
    await fireEvent.click(within(row).getByTestId('contract-refer'));

    await waitFor(() =>
      expect(within(rowOf(BANDEIRA.id)).getByTestId('contract-sent').textContent).toContain(
        'With Margem Certa (exemplo)'
      )
    );

    expect(posted).toEqual([{ body: { partner_id: MARGEM.id }, path: 'contracts/9/referrals' }]);
    expect(alertRequests).toBe(2);
    expect(within(rowOf(BANDEIRA.id)).queryByTestId('contract-refer')).toBeNull();
    expect(screen.getByTestId('contracts-referred').textContent).toContain('2');
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }));
  });

  it('A20: a contract already sent says "Com <parceiro>" and offers no picker', async () => {
    renderContracts();

    await screen.findAllByTestId('contract');

    const row = rowOf(WITH_ANDRADE.id);

    expect(within(row).getByTestId('contract-sent').textContent).toContain('With Andrade Tributário (exemplo)');
    expect(within(row).queryByTestId('contract-refer')).toBeNull();
  });

  it('A20: without a legal partner the row says "Sem parceiro jurídico na rede."', async () => {
    answers.partners = [];
    renderContracts();

    await screen.findAllByTestId('contract');

    expect(within(rowOf(BANDEIRA.id)).getByTestId('contract-no-partner').textContent).toContain(
      'No legal partner in the network.'
    );
    expect(within(rowOf(BANDEIRA.id)).queryByTestId('contract-refer')).toBeNull();
  });

  it('says a refusal the API names by code on the row', async () => {
    answers.partners = [{ ...ANDRADE, id: 409 }];
    renderContracts();

    await screen.findAllByTestId('contract');
    await fireEvent.click(within(rowOf(BANDEIRA.id)).getByTestId('contract-refer'));

    const alert = await within(rowOf(BANDEIRA.id)).findByRole('alert');

    expect(alert.textContent).toContain('This contract was just sent to this partner');
  });

  it('A20: "Cláusula incluída" marks the contract and the row leaves the list', async () => {
    renderContracts();

    await screen.findAllByTestId('contract');
    await fireEvent.click(within(rowOf(REFUSED.id)).getByTestId('contract-clause'));

    expect(within(rowOf(REFUSED.id)).getByTestId('contract-clause-confirm').textContent).toContain(
      'Atacado Serra Azul Ltda'
    );
    expect(posted).toEqual([]);

    await fireEvent.click(within(rowOf(REFUSED.id)).getByTestId('contract-clause-yes'));

    await waitFor(() => expect(idsShown()).toEqual(['9', '18', '15']));

    expect(posted).toEqual([{ body: null, path: 'contracts/12/clause' }]);
    expect(plain(screen.getByTestId('contracts-at-risk').textContent)).toContain('3 of 40');
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'success' }));
  });

  it('A20: "Cláusula incluída" asks first, and "Cancelar" keeps the row and marks nothing', async () => {
    renderContracts();

    await screen.findAllByTestId('contract');
    await fireEvent.click(within(rowOf(REFUSED.id)).getByTestId('contract-clause'));
    await fireEvent.click(within(rowOf(REFUSED.id)).getByTestId('contract-clause-no'));

    expect(within(rowOf(REFUSED.id)).queryByTestId('contract-clause-confirm')).toBeNull();
    expect(within(rowOf(REFUSED.id)).getByTestId('contract-clause')).toBeTruthy();
    expect(posted).toEqual([]);
    expect(idsShown()).toEqual(['9', '12', '18', '15']);
  });

  it('R19: says so when no contract crosses the transition without the clause', async () => {
    answers.alert = { contracts: [], summary: { ...CONTRACTS_FIXTURE.summary, at_risk: 0, referred: 0 } };
    renderContracts();

    expect((await screen.findByTestId('contracts-empty')).textContent).toContain(
      'No contract crosses the transition without a clause.'
    );
  });

  it('offers a retry when the alert does not load', async () => {
    alertFails = true;
    renderContracts();

    const failure = await screen.findByRole('alert');

    expect(failure.textContent).toContain('Could not load the contracts.');

    alertFails = false;
    await fireEvent.click(within(failure).getByRole('button'));

    await screen.findAllByTestId('contract');
    expect(alertRequests).toBe(2);
  });

  it('R18: "Importar CSV" opens the import in the dialog', async () => {
    renderContracts();

    await screen.findAllByTestId('contract');
    await fireEvent.click(screen.getByTestId('contracts-import'));

    expect(screen.getByTestId('dialog').getAttribute('data-title')).toBe('contracts.import.title');
    expect(screen.getByTestId('import-form')).toBeTruthy();
  });
});
