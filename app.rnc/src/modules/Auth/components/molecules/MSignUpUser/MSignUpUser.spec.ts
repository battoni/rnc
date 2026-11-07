import { flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type * as VueRouter from 'vue-router';
import { mountWithPlugins } from '@/test/mount';

const { push, registerUserService } = vi.hoisted(() => ({
  push: vi.fn(),
  registerUserService: vi.fn(),
}));

vi.mock('@AuthModule', () => ({ RegisterUserService: registerUserService }));

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<typeof VueRouter>()),
  useRoute: () => ({ query: {} }),
  useRouter: () => ({ push }),
}));

import MSignUpUser from './MSignUpUser.vue';

const VALUES = {
  birthDate: '07/03/1990',
  cpf: '123.456.789-09',
  email: 'ana@example.test',
  firstName: 'Ana',
  lastName: 'Lima',
  password: 'secret123',
  passwordConfirmation: 'secret123',
  phone: '',
  termsAccepted: true,
};

/*
 * The form itself is stubbed: what is under test is what `onSubmit` SENDS, and the real PrimeVue
 * Form would need every masked field typed through jsdom to get there.
 */
const formStub = {
  name: 'Form',
  emits: ['submit'],
  template: '<form><slot v-bind="{}" /></form>',
};

function mountForm() {
  return mountWithPlugins(MSignUpUser, {
    global: { stubs: { Form: formStub }, renderStubDefaultSlot: false },
    shallow: true,
  });
}

async function submit(wrapper: ReturnType<typeof mountForm>, values: Record<string, unknown>) {
  wrapper.findComponent(formStub).vm.$emit('submit', { valid: true, values });
  await flushPromises();
}

describe('MSignUpUser — the birth date it sends', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    /*
     * East of UTC on purpose. A typed day becomes local midnight, and local midnight in Tokyo is
     * still YESTERDAY in UTC — so a `toISOString()` in the path sends the day before. (West of
     * UTC the same slip happens to "now" after 21:00, which is the helper's own spec.)
     */
    // `stubEnv` sets the zone Node reads on every date call; `unstubAllEnvs` puts it back.
    vi.stubEnv('TZ', 'Asia/Tokyo');
    registerUserService.mockResolvedValue({ data: {} });
  });

  afterEach(() => vi.unstubAllEnvs());

  it('turns the masked day into the same calendar day, not a UTC one', async () => {
    await submit(mountForm(), VALUES);

    expect(registerUserService).toHaveBeenCalledWith(expect.objectContaining({ birthDate: '1990-03-07' }));
  });

  it('sends a day that does not exist as typed, so the API refuses it on the field', async () => {
    await submit(mountForm(), { ...VALUES, birthDate: '31/02/1990' });

    expect(registerUserService).toHaveBeenCalledWith(expect.objectContaining({ birthDate: '31/02/1990' }));
  });
});
