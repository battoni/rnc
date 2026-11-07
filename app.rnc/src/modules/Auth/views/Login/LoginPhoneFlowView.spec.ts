import { flushPromises } from '@vue/test-utils';
import Message from 'primevue/message';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import type * as VueRouter from 'vue-router';
import { mountWithPlugins } from '@/test/mount';

const { push, query, setUserAndPermissions, toastAdd } = vi.hoisted(() => ({
  push: vi.fn(),
  toastAdd: vi.fn(),
  query: { value: {} as Record<string, string> },
  setUserAndPermissions: vi.fn(),
}));

vi.mock('@UserModule', () => ({ useUserStore: () => ({ setUserAndPermissions }) }));

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<typeof VueRouter>()),
  useRoute: () => ({ query: query.value }),
  useRouter: () => ({ push }),
}));

vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));

import LoginPhoneFlowView from './LoginPhoneFlowView.vue';

const REQUESTED = { dev_code: '123456', expires_in: 600, phone: '5511999998888' };
const USER = { id: 7, name: 'Ana', permissions: ['home.read'] };

const layoutStub = { props: ['title'], template: '<div class="layout-stub" :data-title="title"><slot /></div>' };
const phoneStub = { name: 'MLoginPhone', props: ['initialPhone'], template: '<div class="phone-step" />' };
const codeStub = { name: 'MLoginPhoneCode', props: ['devCode', 'phone'], template: '<div class="code-step" />' };

function mountView() {
  return mountWithPlugins(LoginPhoneFlowView, {
    global: {
      components: { Message },
      stubs: { AuthFlowLayout: layoutStub, MLoginPhone: phoneStub, MLoginPhoneCode: codeStub, RouterLink: true },
    },
  });
}

async function reachCodeStep(wrapper: ReturnType<typeof mountView>) {
  wrapper.findComponent(phoneStub).vm.$emit('onSuccess', REQUESTED, '(11) 99999-8888');
  await flushPromises();
}

describe('LoginPhoneFlowView', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    query.value = {};
  });

  it('starts at the phone and moves to the code with what the API answered', async () => {
    const wrapper = mountView();

    expect(wrapper.find('.phone-step').exists()).toBe(true);

    await reachCodeStep(wrapper);

    const codeStep = wrapper.findComponent(codeStub);

    expect(wrapper.find('.phone-step').exists()).toBe(false);
    expect(codeStep.props()).toEqual({ devCode: '123456', phone: '5511999998888' });
    expect(wrapper.get('.layout-stub').attributes('data-title')).toBe('Sign in with code');
  });

  it('stores the session with its permissions and goes home', async () => {
    const wrapper = mountView();

    await reachCodeStep(wrapper);
    wrapper.findComponent(codeStub).vm.$emit('onSuccess', USER);
    await flushPromises();

    // BOTH arguments: passing only the user leaves the menu empty for someone who has access.
    expect(setUserAndPermissions).toHaveBeenCalledWith(USER, ['home.read']);
    expect(push).toHaveBeenCalledWith({ name: 'home' });
  });

  it('returns to the page the guard parked, and never to another host', async () => {
    query.value = { redirect: '/ideas' };
    const wrapper = mountView();

    await reachCodeStep(wrapper);
    wrapper.findComponent(codeStub).vm.$emit('onSuccess', USER);
    await flushPromises();

    expect(push).toHaveBeenCalledWith('/ideas');

    query.value = { redirect: '//evil.test' };
    const other = mountView();

    await reachCodeStep(other);
    other.findComponent(codeStub).vm.$emit('onSuccess', USER);
    await flushPromises();

    expect(push).toHaveBeenLastCalledWith({ name: 'home' });
  });

  it('goes back to the phone, keeping what was typed, to change the number', async () => {
    const wrapper = mountView();

    await reachCodeStep(wrapper);
    wrapper.findComponent(codeStub).vm.$emit('onChangePhone');
    await flushPromises();

    expect(wrapper.findComponent(phoneStub).props('initialPhone')).toBe('(11) 99999-8888');
    expect(wrapper.find('[data-testid="login-phone-code-expired"]').exists()).toBe(false);
  });

  it('says the code expired above the phone step', async () => {
    const wrapper = mountView();

    await reachCodeStep(wrapper);
    wrapper.findComponent(codeStub).vm.$emit('onCodeExpired');
    await flushPromises();

    expect(wrapper.find('.phone-step').exists()).toBe(true);
    expect(wrapper.get('[data-testid="login-phone-code-expired"]').text()).toContain('expired');
  });

  it('goes back to the phone, still filled in, and says why when the code was burned', async () => {
    const wrapper = mountView();

    await reachCodeStep(wrapper);
    wrapper.findComponent(codeStub).vm.$emit('onCodeLocked');
    await flushPromises();

    expect(wrapper.findComponent(phoneStub).props('initialPhone')).toBe('(11) 99999-8888');
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({
        severity: 'info',
        summary: 'Code blocked',
        detail: expect.stringContaining('Request a new one'),
      })
    );
    expect(wrapper.find('[data-testid="login-phone-code-expired"]').exists()).toBe(false);
  });
});
