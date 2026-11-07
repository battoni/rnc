import { defineComponent, h, reactive } from 'vue';
import { flushPromises } from '@vue/test-utils';
import { delay, http, HttpResponse } from 'msw';
// Register PrimeVue components explicitly — auto-import only works in Vite, not Vitest.
import InputMask from 'primevue/inputmask';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { describe, expect, it } from 'vitest';
import type { AddressFields } from '@Interfaces';
import { mountWithPlugins } from '@/test/mount';
import { server } from '@/test/msw/server';
import MAddressFields from './MAddressFields.vue';

const VIACEP_URL = 'https://viacep.com.br/ws';

const COMPONENTS = { InputMask, InputText, Select };

const SE_ADDRESS = {
  bairro: 'Sé',
  cep: '01001-000',
  localidade: 'São Paulo',
  logradouro: 'Praça da Sé',
  uf: 'SP',
};

function buildAddress(overrides: Partial<AddressFields> = {}): AddressFields {
  return reactive({
    cep: '',
    city: '',
    complement: '',
    district: '',
    number: '',
    state: '',
    street: '',
    ...overrides,
  });
}

function mountFields(modelValue: AddressFields) {
  return mountWithPlugins(MAddressFields, {
    attachTo: document.body,
    global: { components: COMPONENTS },
    props: { modelValue },
  });
}

function field(wrapper: ReturnType<typeof mountFields>, name: string) {
  return wrapper.find(`input[name="${name}"]`);
}

/** Types a CEP the way a person does: the mask fills, and a full CEP fires `complete`. */
async function typeCep(wrapper: ReturnType<typeof mountFields>, cep: string) {
  await field(wrapper, 'cep').setValue(cep);
}

describe('MAddressFields', () => {
  it('renders every field of a street address, each label pointing at its input', () => {
    const wrapper = mountFields(buildAddress());

    for (const name of ['cep', 'street', 'number', 'complement', 'district', 'city']) {
      const input = field(wrapper, name);

      expect(input.exists()).toBe(true);
      expect(wrapper.find(`label[for="${input.attributes('id')}"]`).exists()).toBe(true);
    }

    const stateLabel = wrapper.findAll('label').find((label) => label.text() === 'State');

    expect(document.getElementById(stateLabel!.attributes('for')!)).not.toBeNull();

    wrapper.unmount();
  });

  it('gives two instances on one page different ids', () => {
    const TwoForms = defineComponent({
      render: () => [
        h(MAddressFields, { modelValue: buildAddress() }),
        h(MAddressFields, { modelValue: buildAddress() }),
      ],
    });

    const wrapper = mountWithPlugins(TwoForms, { global: { components: COMPONENTS } });
    const cepIds = wrapper.findAll('input[name="cep"]').map((input) => input.attributes('id'));

    expect(cepIds).toHaveLength(2);
    expect(cepIds[0]).not.toBe(cepIds[1]);

    wrapper.unmount();
  });

  it('prefills the inputs from the model', () => {
    const wrapper = mountFields(buildAddress({ number: '100', street: 'Praça da Sé' }));

    expect((field(wrapper, 'street').element as HTMLInputElement).value).toBe('Praça da Sé');
    expect((field(wrapper, 'number').element as HTMLInputElement).value).toBe('100');

    wrapper.unmount();
  });

  it('fills street, district, city and state from ViaCEP when the CEP is complete', async () => {
    server.use(http.get(`${VIACEP_URL}/01001000/json/`, () => HttpResponse.json(SE_ADDRESS)));

    const address = buildAddress({ number: '10' });
    const wrapper = mountFields(address);

    await typeCep(wrapper, '01001000');
    await flushPromises();

    expect(address).toMatchObject({
      cep: '01001-000',
      city: 'São Paulo',
      district: 'Sé',
      number: '10',
      state: 'SP',
      street: 'Praça da Sé',
    });
    expect(wrapper.find('[role="status"]').exists()).toBe(false);

    wrapper.unmount();
  });

  it('keeps what the person typed where ViaCEP answers blank', async () => {
    server.use(
      http.get(`${VIACEP_URL}/13100000/json/`, () =>
        HttpResponse.json({ bairro: '', cep: '13100-000', localidade: 'Campinas', logradouro: '', uf: 'SP' })
      )
    );

    const address = buildAddress({ district: 'Centro', street: 'Rua Um' });
    const wrapper = mountFields(address);

    await typeCep(wrapper, '13100000');
    await flushPromises();

    expect(address).toMatchObject({ city: 'Campinas', district: 'Centro', state: 'SP', street: 'Rua Um' });

    wrapper.unmount();
  });

  it('says it is looking the address up while ViaCEP answers', async () => {
    server.use(
      http.get(`${VIACEP_URL}/01001000/json/`, async () => {
        await delay(50);

        return HttpResponse.json(SE_ADDRESS);
      })
    );

    const wrapper = mountFields(buildAddress());

    await typeCep(wrapper, '01001000');

    const loadingLine = wrapper.find('[role="status"]');

    expect(loadingLine.text()).toBe('Looking up the address…');

    // A sentence someone reads is `text-muted` or stronger — `subtle` is for icons (celer-06).
    expect(loadingLine.classes()).toContain('text-muted');

    expect(loadingLine.classes()).not.toContain('text-subtle');

    await delay(80);
    await flushPromises();

    expect(wrapper.find('[role="status"]').exists()).toBe(false);

    wrapper.unmount();
  });

  it('leaves the fields alone and asks for them by hand when ViaCEP does not know the CEP', async () => {
    server.use(http.get(`${VIACEP_URL}/99999999/json/`, () => HttpResponse.json({ erro: true })));

    const address = buildAddress({ city: 'Recife', street: 'Rua Dois' });
    const wrapper = mountFields(address);

    await typeCep(wrapper, '99999999');
    await flushPromises();

    expect(address).toMatchObject({ city: 'Recife', district: '', state: '', street: 'Rua Dois' });
    expect(wrapper.find('[role="status"]').text()).toBe(
      'We could not fill in the address from this CEP. Type it below.'
    );

    wrapper.unmount();
  });

  it('says the same when ViaCEP cannot be reached', async () => {
    server.use(http.get(`${VIACEP_URL}/01001000/json/`, () => HttpResponse.error()));

    const address = buildAddress();
    const wrapper = mountFields(address);

    await typeCep(wrapper, '01001000');
    await flushPromises();

    expect(address.street).toBe('');
    expect(wrapper.find('[role="status"]').text()).toBe(
      'We could not fill in the address from this CEP. Type it below.'
    );

    wrapper.unmount();
  });

  it('does not let a slow answer for an earlier CEP overwrite the later one', async () => {
    server.use(
      http.get(`${VIACEP_URL}/01001000/json/`, async () => {
        await delay(80);

        return HttpResponse.json(SE_ADDRESS);
      }),
      http.get(`${VIACEP_URL}/20040002/json/`, () =>
        HttpResponse.json({ bairro: 'Centro', localidade: 'Rio de Janeiro', logradouro: 'Rua da Assembleia', uf: 'RJ' })
      )
    );

    const address = buildAddress();
    const wrapper = mountFields(address);

    await typeCep(wrapper, '01001000');
    await typeCep(wrapper, '20040002');
    await delay(120);
    await flushPromises();

    expect(address).toMatchObject({ city: 'Rio de Janeiro', state: 'RJ', street: 'Rua da Assembleia' });

    wrapper.unmount();
  });
});
