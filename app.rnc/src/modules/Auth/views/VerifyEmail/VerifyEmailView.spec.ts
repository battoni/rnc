import Form from '@primevue/forms/form';
import FormField from '@primevue/forms/formfield';
import { flushPromises } from '@vue/test-utils';
// The real PrimeVue components, registered by hand: auto-import is a Vite plugin and does
// not run under Vitest, and stubbing `Form` would mean testing a stub's idea of validation.
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Message from 'primevue/message';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type * as VueRouter from 'vue-router';
import { mountWithPlugins } from '@/test/mount';

const { push, requestEmailOtpService, setUserAndPermissions, verifyEmailService } = vi.hoisted(() => ({
  push: vi.fn(),
  requestEmailOtpService: vi.fn(),
  setUserAndPermissions: vi.fn(),
  verifyEmailService: vi.fn(),
}));

vi.mock('@AuthModule', () => ({
  RequestEmailOtpService: requestEmailOtpService,
  SIGN_UP_CODE_RESEND_COOLDOWN_SECONDS: 60,
  VerifyEmailService: verifyEmailService,
}));

vi.mock('@UserModule', () => ({
  USER_STATUS: { ACTIVE: 'active', INACTIVE: 'inactive' },
  useUserStore: () => ({ setUserAndPermissions }),
}));

// Partial: `@Helpers` (the login path) builds a router at import time and needs the real exports.
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<typeof VueRouter>()),
  useRoute: () => ({ query: { email: 'ana@example.test' } }),
  useRouter: () => ({ push }),
}));

const toastAdd = vi.fn();
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));

import VerifyEmailView from './VerifyEmailView.vue';

const layoutStub = { props: ['title'], template: '<div class="layout-stub"><slot /></div>' };

function mountView() {
  return mountWithPlugins(VerifyEmailView, {
    global: {
      components: { Button, Form, FormField, InputText, Message },
      stubs: { AuthFlowLayout: layoutStub },
    },
  });
}

describe('VerifyEmailView', () => {
  afterEach(() => vi.useRealTimers());

  beforeEach(() => {
    vi.clearAllMocks();
    // Only the clock and the interval: faking every timer would freeze the Form's own promises.
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] });
    verifyEmailService.mockResolvedValue({
      data: { id: 1, permissions: ['home.read', 'ideas.read'], username: 'ana@example.test' },
    });
    requestEmailOtpService.mockResolvedValue({ data: { sent: true } });
  });

  it('says which address the code went to', async () => {
    const wrapper = mountView();
    await flushPromises();

    // Without this the screen is six anonymous boxes: the one thing it must answer is
    // "which inbox am I supposed to be looking at".
    expect(wrapper.text()).toContain('ana@example.test');
  });

  it('signs the person in and leaves once the code checks out', async () => {
    const wrapper = mountView();

    await wrapper.get('#code').setValue('123456');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(verifyEmailService).toHaveBeenCalledWith({ code: '123456', email: 'ana@example.test' });
    // BOTH arguments: the store keeps the user and the permissions apart, and passing only
    // the user leaves the menu empty for someone who has access — with no error anywhere.
    expect(setUserAndPermissions).toHaveBeenCalledWith(
      { id: 1, permissions: ['home.read', 'ideas.read'], username: 'ana@example.test' },
      ['home.read', 'ideas.read']
    );
    expect(push).toHaveBeenCalledWith({ name: 'home' });
  });

  it('leaves an account parked for approval signed out, and says what happens next', async () => {
    verifyEmailService.mockResolvedValue({ data: { id: 1, status: 'inactive', username: 'ana@example.test' } });

    const wrapper = mountView();

    await wrapper.get('#code').setValue('123456');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    // The API created the account and opened no session. Storing it and going home would bounce
    // off the guard onto the login screen with no word about why.
    expect(setUserAndPermissions).not.toHaveBeenCalled();
    expect(push).not.toHaveBeenCalledWith({ name: 'home' });
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'info', summary: expect.stringContaining('approve') })
    );
  });

  it('does not send a code that is not six digits', async () => {
    const wrapper = mountView();

    await wrapper.get('#code').setValue('12');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(verifyEmailService).not.toHaveBeenCalled();
  });

  it('stays quiet on a wrong code — the field already says it', async () => {
    verifyEmailService.mockRejectedValue({ response: { status: 422 } });

    const wrapper = mountView();
    await wrapper.get('#code').setValue('000000');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    // A red banner for the most ordinary outcome of typing six digits trains people to
    // ignore red banners.
    expect(toastAdd).not.toHaveBeenCalled();
    expect(push).not.toHaveBeenCalled();
  });

  it('says what is wrong under the field, translating the key the API sent', async () => {
    verifyEmailService.mockRejectedValue({
      response: { status: 422, data: { errors: { code: ['auth.flow.signUp.verify.invalidCode'] } } },
    });

    const wrapper = mountView();
    await wrapper.get('#code').setValue('000000');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    // Without this the screen is SILENT in the commonest case of all: a mistyped code. The
    // resolver only knows the shape; whether the digits are right is the API's answer, and
    // that answer has to show. The API sends a KEY, not a sentence — it does not know the
    // language the person is reading in.
    expect(wrapper.text()).toContain('That code is not right');
    expect(toastAdd).not.toHaveBeenCalled();
  });

  it('does complain when the API is unreachable', async () => {
    verifyEmailService.mockRejectedValue({ response: { status: 500 } });

    const wrapper = mountView();
    await wrapper.get('#code').setValue('000000');
    await wrapper.get('form').trigger('submit');
    await flushPromises();

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }));
  });

  it('asks for another code without revealing whether the address is registered', async () => {
    const wrapper = mountView();

    vi.advanceTimersByTime(60_000);
    await flushPromises();

    await wrapper.get('[data-testid="verify-email-resend"]').trigger('click');
    await flushPromises();

    expect(requestEmailOtpService).toHaveBeenCalledWith({ email: 'ana@example.test' });
    // The wording is conditional on purpose — a confident "sent!" would confirm the
    // address exists here, which is precisely what the API refuses to say.
    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'success', summary: expect.stringContaining('If there is') })
    );
  });

  it('holds the resend for a minute after the code went out, and says how long is left', async () => {
    const wrapper = mountView();
    await flushPromises();

    const resend = wrapper.get('[data-testid="verify-email-resend"]');

    expect(resend.attributes('disabled')).toBeDefined();
    expect(wrapper.get('[data-testid="verify-email-resend-countdown"]').text()).toContain('60');
    // A sentence someone reads is `text-muted`: `subtle` measured 4.09:1 here, under the 4.5 floor.
    expect(wrapper.get('[data-testid="verify-email-resend-countdown"]').classes()).toContain('text-muted');

    await resend.trigger('click');
    await flushPromises();

    expect(requestEmailOtpService).not.toHaveBeenCalled();
  });

  it('frees the resend by the wall clock, even after the phone suspended the timers', async () => {
    const wrapper = mountView();

    // Two minutes in the mail app: the clock moved, no tick ran. One tick is enough to catch up.
    vi.setSystemTime(Date.now() + 120_000);
    vi.advanceTimersByTime(1000);
    await flushPromises();

    expect(wrapper.get('[data-testid="verify-email-resend"]').attributes('disabled')).toBeUndefined();
    expect(wrapper.find('[data-testid="verify-email-resend-countdown"]').exists()).toBe(false);
  });

  it('restarts the wait after a resend', async () => {
    const wrapper = mountView();

    vi.advanceTimersByTime(60_000);
    await flushPromises();

    await wrapper.get('[data-testid="verify-email-resend"]').trigger('click');
    await flushPromises();

    expect(wrapper.get('[data-testid="verify-email-resend"]').attributes('disabled')).toBeDefined();
  });

  it('says to wait, in words, when the API throttles the resend', async () => {
    requestEmailOtpService.mockRejectedValue({ response: { status: 429 } });

    const wrapper = mountView();

    vi.advanceTimersByTime(60_000);
    await flushPromises();

    await wrapper.get('[data-testid="verify-email-resend"]').trigger('click');
    await flushPromises();

    expect(toastAdd).toHaveBeenCalledWith(
      expect.objectContaining({ severity: 'warn', summary: 'Please wait a moment and try again.' })
    );
    expect(toastAdd).not.toHaveBeenCalledWith(expect.objectContaining({ severity: 'error' }));
  });
});
