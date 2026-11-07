import { flushPromises } from '@vue/test-utils';
import Button from 'primevue/button';
import InputOtp from 'primevue/inputotp';
import Message from 'primevue/message';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';

const { requestPhoneCodeService, verifyPhoneCodeService } = vi.hoisted(() => ({
  requestPhoneCodeService: vi.fn(),
  verifyPhoneCodeService: vi.fn(),
}));

vi.mock('@AuthModule', () => ({
  RequestPhoneCodeService: requestPhoneCodeService,
  VerifyPhoneCodeService: verifyPhoneCodeService,
}));

import MLoginPhoneCode from './MLoginPhoneCode.vue';

const PHONE = '5511999998888';

function mountStep(props: Record<string, unknown> = {}) {
  return mountWithPlugins(MLoginPhoneCode, {
    global: { components: { Button, InputOtp, Message } },
    props: { phone: PHONE, ...props },
  });
}

async function typeCode(wrapper: ReturnType<typeof mountStep>, code: string) {
  wrapper.findComponent(InputOtp).vm.$emit('update:modelValue', code);
  await flushPromises();
}

function refusal(status: number, data: Record<string, unknown>) {
  return Promise.reject({ response: { data, status } });
}

describe('MLoginPhoneCode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers();
    verifyPhoneCodeService.mockResolvedValue({ data: { id: 7, name: 'Ana', permissions: ['home.read'] } });
    requestPhoneCodeService.mockResolvedValue({ data: { expires_in: 600, phone: PHONE } });
  });

  afterEach(() => vi.useRealTimers());

  it('says where the code went, as a person writes the number', () => {
    expect(mountStep().get('[data-testid="login-phone-code-phone"]').text()).toBe('+55 (11) 99999-8888');
  });

  it('lets the phone offer the code from the message', () => {
    const inputs = mountStep().findAll('input');

    expect(inputs).toHaveLength(6);
    expect(inputs[0]?.attributes('autocomplete')).toBe('one-time-code');
  });

  it('shows the development code only when the API exposed one', () => {
    expect(mountStep().find('[data-testid="login-phone-code-dev-code"]').exists()).toBe(false);
    expect(mountStep({ devCode: '123456' }).get('[data-testid="login-phone-code-dev-code"]').text()).toContain(
      '123456'
    );
  });

  it('checks the code as soon as the sixth digit arrives and hands the user up', async () => {
    const wrapper = mountStep();

    await typeCode(wrapper, '123456');

    expect(verifyPhoneCodeService).toHaveBeenCalledWith({ code: '123456', phone: PHONE });
    expect(wrapper.emitted('onSuccess')?.[0]).toEqual([{ id: 7, name: 'Ana', permissions: ['home.read'] }]);
  });

  it('does not ask before the code is complete', async () => {
    const wrapper = mountStep();

    await typeCode(wrapper, '12345');

    expect(verifyPhoneCodeService).not.toHaveBeenCalled();
  });

  it('clears the boxes and keeps the refusal under them for a wrong code', async () => {
    verifyPhoneCodeService.mockReturnValue(refusal(422, { code: 'invalid_code' }));
    const wrapper = mountStep();

    await typeCode(wrapper, '000000');

    expect(wrapper.get('[data-testid="login-phone-code-error"]').text()).toBe('Invalid code');
    expect(wrapper.findComponent(InputOtp).props('modelValue')).toBe('');
    expect(wrapper.emitted('onSuccess')).toBeUndefined();
  });

  it('sends the person back for a new code when this one expired', async () => {
    verifyPhoneCodeService.mockReturnValue(refusal(422, { code: 'code_expired' }));
    const wrapper = mountStep();

    await typeCode(wrapper, '123456');

    expect(wrapper.emitted('onCodeExpired')).toHaveLength(1);
  });

  it('hands the burned code up instead of asking for another guess', async () => {
    verifyPhoneCodeService.mockReturnValue(refusal(422, { code: 'code_locked' }));
    const wrapper = mountStep();

    await typeCode(wrapper, '000000');

    expect(wrapper.emitted('onCodeLocked')).toHaveLength(1);
    expect(wrapper.find('[data-testid="login-phone-code-error"]').exists()).toBe(false);
  });

  it('names a deactivated account with the key the API sent', async () => {
    verifyPhoneCodeService.mockReturnValue(refusal(422, { errors: { phone: ['auth.accountInactive'] } }));
    const wrapper = mountStep();

    await typeCode(wrapper, '123456');

    expect(wrapper.get('[data-testid="login-phone-code-error"]').text()).toBe(
      'This account has been deactivated. Contact an administrator.'
    );
  });

  it('holds the resend until the countdown ends, then resends and restarts it', async () => {
    const wrapper = mountStep({ resendCooldownSeconds: 2 });
    const resend = wrapper.get('[data-testid="login-phone-code-resend"]');

    expect(resend.attributes('disabled')).toBeDefined();

    await vi.advanceTimersByTimeAsync(2000);

    expect(resend.attributes('disabled')).toBeUndefined();
    expect(wrapper.find('[data-testid="login-phone-code-countdown"]').exists()).toBe(false);

    await resend.trigger('click');
    await flushPromises();

    expect(requestPhoneCodeService).toHaveBeenCalledWith({ phone: PHONE });
    expect(wrapper.emitted('onResent')).toHaveLength(1);
    expect(wrapper.get('[data-testid="login-phone-code-countdown"]').text()).toContain('2');
  });

  it('frees the resend by the wall clock after the phone suspended the timers', async () => {
    const wrapper = mountStep({ resendCooldownSeconds: 60 });

    // Two minutes reading the message in another app: the clock moved, no tick ran.
    vi.setSystemTime(Date.now() + 120_000);
    await vi.advanceTimersByTimeAsync(1000);

    expect(wrapper.get('[data-testid="login-phone-code-resend"]').attributes('disabled')).toBeUndefined();
    expect(wrapper.find('[data-testid="login-phone-code-countdown"]').exists()).toBe(false);
  });

  it('says a delivery failure on resend in words, not as a crash', async () => {
    requestPhoneCodeService.mockReturnValue(refusal(503, { code: 'delivery_failed' }));
    const wrapper = mountStep({ resendCooldownSeconds: 0 });

    await wrapper.get('[data-testid="login-phone-code-resend"]').trigger('click');
    await flushPromises();

    expect(wrapper.get('[data-testid="login-phone-code-error"]').text()).toContain('could not send the code');
  });
});
