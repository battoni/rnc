import { defineComponent } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mountWithPlugins } from '@/test/mount';

const toastAdd = vi.fn();

vi.mock('primevue/usetoast', () => ({
  useToast: () => ({ add: toastAdd }),
}));

import { useLoginError } from './useLoginError';

/*
 * The three login forms each cover the 429 through their own integration spec. What only lives
 * here is the SPLIT: which refusals the form is allowed to restate under the field, and which
 * ones must stay in the toast alone. Pinning "something went wrong" beneath a password box tells
 * the reader nothing they can act on, so `apiError` stays null for anything unrecognised.
 */
function callWith(error: unknown) {
  let captured!: ReturnType<typeof useLoginError>;

  const host = defineComponent({
    setup() {
      captured = useLoginError();
      return () => null;
    },
  });

  const wrapper = mountWithPlugins(host);

  captured.onLoginError(error as never);

  return { apiError: captured.apiError, wrapper };
}

describe('useLoginError', () => {
  beforeEach(() => toastAdd.mockClear());

  it('restates a 422 whose message is an i18n key', () => {
    const { apiError } = callWith({ response: { status: 422, data: { message: 'auth.login.invalidCredentials' } } });

    expect(apiError.value).toBe('The username or password is incorrect');
    expect(toastAdd).toHaveBeenCalledOnce();
  });

  it('reads the errors bag when the refusal arrived there instead', () => {
    const { apiError } = callWith({
      response: { status: 422, data: { errors: { username: ['auth.login.invalidCredentials'] } } },
    });

    expect(apiError.value).toBe('The username or password is incorrect');
  });

  // Deactivated + correct password is a different key from wrong credentials — the API says so;
  // the form only translates. Guessing inactive client-side would undo the anti-enumeration rule.
  it('restates a deactivated-account refusal under the field', () => {
    const { apiError } = callWith({
      response: { status: 422, data: { errors: { username: ['auth.accountInactive'] } } },
    });

    expect(apiError.value).toBe('This account has been deactivated. Contact an administrator.');
    expect(toastAdd).toHaveBeenCalledOnce();
  });

  it("answers a 429 in the reader's language, never Laravel's prose", () => {
    const { apiError } = callWith({ response: { status: 429, data: { message: 'Too Many Attempts.' } } });

    expect(apiError.value).toBe('Too many attempts. Wait about 1 minute and try again.');
    expect(apiError.value).not.toContain('Too Many Attempts.');
  });

  it('keeps an unrecognised failure in the toast and off the field', () => {
    const { apiError } = callWith({ response: { status: 500 } });

    expect(apiError.value).toBeNull();
    expect(toastAdd).toHaveBeenCalledOnce();
  });

  it('treats a request that never got a response the same way', () => {
    const { apiError } = callWith({ message: 'Network Error' });

    expect(apiError.value).toBeNull();
    expect(toastAdd).toHaveBeenCalledOnce();
  });

  it('does not restate a 422 that carries no message at all', () => {
    // Nothing to say under the field; the bag was empty, so this is not a refusal we can name.
    const { apiError } = callWith({ response: { status: 422, data: {} } });

    expect(apiError.value).toBeNull();
  });
});
