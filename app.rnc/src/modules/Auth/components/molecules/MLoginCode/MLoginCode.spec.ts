import Form from '@primevue/forms/form';
import FormField from '@primevue/forms/formfield';
import { flushPromises } from '@vue/test-utils';
import Button from 'primevue/button';
import InputOtp from 'primevue/inputotp';
import Message from 'primevue/message';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type * as VueRouter from 'vue-router';
import { mountWithPlugins } from '@/test/mount';

const { push, requestEmailOtpService, toastAdd, verifyLoginCodeService } = vi.hoisted(() => ({
  push: vi.fn(),
  requestEmailOtpService: vi.fn(),
  toastAdd: vi.fn(),
  verifyLoginCodeService: vi.fn(),
}));

vi.mock('@AuthModule', () => ({
  RequestEmailOtpService: requestEmailOtpService,
  VerifyLoginCodeService: verifyLoginCodeService,
}));

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<typeof VueRouter>()),
  useRoute: () => ({ query: { phone: '5511999998888' } }),
  useRouter: () => ({ push }),
}));

vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));

import MLoginCode from './MLoginCode.vue';

const START = new Date('2026-09-16T12:00:00Z').getTime();

function mountStep() {
  return mountWithPlugins(MLoginCode, {
    global: { components: { Button, Form, FormField, InputOtp, Message } },
  });
}

function resendButton(wrapper: ReturnType<typeof mountStep>) {
  const button = wrapper.findAll('button').find((candidate) => candidate.text() === 'Resend code');

  if (!button) throw new Error('resend button not found');

  return button;
}

describe('MLoginCode', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.useFakeTimers({ toFake: ['Date', 'setInterval', 'clearInterval'] });
    vi.setSystemTime(START);
    requestEmailOtpService.mockResolvedValue({ data: {} });
  });

  afterEach(() => vi.useRealTimers());

  it('asks the person to wait while the minute is running, without calling the API', async () => {
    const wrapper = mountStep();

    await resendButton(wrapper).trigger('click');
    await flushPromises();

    expect(requestEmailOtpService).not.toHaveBeenCalled();
    // A wait is a refusal on a rule, not a failure: warn, never the red error (celer-07).
    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'warn', summary: 'Please wait' }));
    expect(wrapper.text()).toContain('01:00');
  });

  it('frees the resend by the wall clock after the phone suspended the timers', async () => {
    const wrapper = mountStep();

    // Two minutes reading the message in another app: the clock moved, no tick ran.
    vi.setSystemTime(START + 120_000);
    vi.advanceTimersByTime(1000);

    await resendButton(wrapper).trigger('click');
    await flushPromises();

    expect(requestEmailOtpService).toHaveBeenCalledWith({ phone: '5511999998888' });
  });

  // An empty string fails the length check too, and the first failing test decides the message:
  // with `length` declared first, an empty field said "Invalid code" instead of asking for one.
  it('asks for the code when the field is submitted empty', async () => {
    const wrapper = mountStep();

    await wrapper.find('form').trigger('submit');
    await flushPromises();

    expect(wrapper.text()).toContain('Code is required');
    expect(wrapper.text()).not.toContain('Invalid code');
    expect(verifyLoginCodeService).not.toHaveBeenCalled();
  });
});
