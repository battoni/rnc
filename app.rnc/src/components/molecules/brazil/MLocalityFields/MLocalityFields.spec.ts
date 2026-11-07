import { reactive } from 'vue';
import { flushPromises } from '@vue/test-utils';
import { delay, http, HttpResponse } from 'msw';
// Register PrimeVue components explicitly — auto-import only works in Vite, not Vitest.
import InputMask from 'primevue/inputmask';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { describe, expect, it } from 'vitest';
import type { LocalityFields } from '@Interfaces';
import { mountWithPlugins } from '@/test/mount';
import { server } from '@/test/msw/server';
import MLocalityFields from './MLocalityFields.vue';

const VIACEP_URL = 'https://viacep.com.br/ws';

const COMPONENTS = { InputMask, InputText, Select };

const NOT_FOUND_MESSAGE = 'We could not fill in the address from this CEP. Type it below.';

function buildLocality(overrides: Partial<LocalityFields> = {}): LocalityFields {
  return reactive({ cep: '', city: '', region: '', state: '', ...overrides });
}

function mountFields(modelValue: LocalityFields, showRegion = false) {
  return mountWithPlugins(MLocalityFields, { global: { components: COMPONENTS }, props: { modelValue, showRegion } });
}

function field(wrapper: ReturnType<typeof mountFields>, name: string) {
  return wrapper.find(`input[name="${name}"]`);
}

describe('MLocalityFields', () => {
  it('renders CEP, city and state, and no street-level field', () => {
    const wrapper = mountFields(buildLocality());

    expect(field(wrapper, 'cep').exists()).toBe(true);
    expect(field(wrapper, 'city').exists()).toBe(true);
    expect(wrapper.text()).toContain('State');
    expect(field(wrapper, 'street').exists()).toBe(false);
    expect(field(wrapper, 'region').exists()).toBe(false);
  });

  it('shows the region only when the parent asks for it, prefilled from the model', () => {
    const wrapper = mountFields(buildLocality({ region: 'Zona Leste' }), true);
    const region = field(wrapper, 'region');

    expect((region.element as HTMLInputElement).value).toBe('Zona Leste');
    expect(wrapper.find(`label[for="${region.attributes('id')}"]`).text()).toBe('Region (optional)');
  });

  it('fills city and state from ViaCEP and nothing else', async () => {
    server.use(
      http.get(`${VIACEP_URL}/01001000/json/`, () =>
        HttpResponse.json({ bairro: 'Sé', localidade: 'São Paulo', logradouro: 'Praça da Sé', uf: 'SP' })
      )
    );

    const locality = buildLocality({ region: 'Centro' });
    const wrapper = mountFields(locality, true);

    await field(wrapper, 'cep').setValue('01001000');
    await flushPromises();

    expect(locality).toEqual({ cep: '01001-000', city: 'São Paulo', region: 'Centro', state: 'SP' });
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
  });

  it('says it is looking the place up in a line someone can read', async () => {
    server.use(
      http.get(`${VIACEP_URL}/01001000/json/`, async () => {
        await delay(50);

        return HttpResponse.json({ cep: '01001-000', localidade: 'São Paulo', uf: 'SP' });
      })
    );

    const wrapper = mountFields(buildLocality());

    await field(wrapper, 'cep').setValue('01001000');

    const loadingLine = wrapper.find('[role="status"]');

    expect(loadingLine.text()).toBe('Looking up the address…');

    // A sentence someone reads is `text-muted` or stronger — `subtle` is for icons (celer-06).
    expect(loadingLine.classes()).toContain('text-muted');

    expect(loadingLine.classes()).not.toContain('text-subtle');

    await delay(80);
    await flushPromises();
  });

  it('keeps the typed city and asks for it by hand when ViaCEP does not know the CEP', async () => {
    server.use(http.get(`${VIACEP_URL}/99999999/json/`, () => HttpResponse.json({ erro: true })));

    const locality = buildLocality({ city: 'Olinda', state: 'PE' });
    const wrapper = mountFields(locality);

    await field(wrapper, 'cep').setValue('99999999');
    await flushPromises();

    expect(locality).toMatchObject({ city: 'Olinda', state: 'PE' });
    expect(wrapper.find('[role="status"]').text()).toBe(NOT_FOUND_MESSAGE);
  });

  it('says the same when ViaCEP cannot be reached', async () => {
    server.use(http.get(`${VIACEP_URL}/01001000/json/`, () => HttpResponse.error()));

    const locality = buildLocality();
    const wrapper = mountFields(locality);

    await field(wrapper, 'cep').setValue('01001000');
    await flushPromises();

    expect(locality.city).toBe('');
    expect(wrapper.find('[role="status"]').text()).toBe(NOT_FOUND_MESSAGE);
  });

  it('does not look anything up while the CEP is incomplete', async () => {
    let requests = 0;

    server.use(
      http.get(`${VIACEP_URL}/:cep/json/`, () => {
        requests += 1;

        return HttpResponse.json({ erro: true });
      })
    );

    const wrapper = mountFields(buildLocality());

    await field(wrapper, 'cep').setValue('0100');
    await flushPromises();

    expect(requests).toBe(0);
    expect(wrapper.find('[role="status"]').exists()).toBe(false);
  });
});
