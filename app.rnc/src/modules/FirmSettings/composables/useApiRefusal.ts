import type { SaveFailure } from '../interfaces';
import { translateError } from '@Helpers';

/**
 * The API's 422 as the settings forms show it (celer-07): each field's first code, translated where
 * the field is — `users.form.roleNotAssignable`, `leads.consentPlaceholderMissing` and the rest have
 * their own locale keys. Anything that is not a 422 is one form-level sentence.
 */
function useApiRefusal() {
  function fieldErrorsOf(failure: SaveFailure): Record<string, string> {
    const errors = failure.response?.data?.errors ?? {};

    return Object.fromEntries(
      Object.entries(errors).map(([field, codes]) => [field, translateError(codes?.[0] ?? 'errors.unexpected')])
    );
  }

  function formErrorOf(failure: SaveFailure): string {
    return failure.translatedMessage ?? translateError('errors.unexpected');
  }

  return { fieldErrorsOf, formErrorOf };
}

export { useApiRefusal };
