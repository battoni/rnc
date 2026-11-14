import { FIRM_PASSWORD_ALPHABET, FIRM_PASSWORD_LENGTH } from '../constants';

/**
 * A team login's one-time password (rnc-4 R31, W81). `auth/users` takes the password from the
 * caller and has no "generate" route, so it is drawn here from the browser's CSPRNG — letters and
 * digits, no look-alikes — sent once, shown once and kept nowhere after the dialog closes.
 */
function useOneTimePassword() {
  function generate(): string {
    const values = crypto.getRandomValues(new Uint32Array(FIRM_PASSWORD_LENGTH));

    return Array.from(values, (value) => FIRM_PASSWORD_ALPHABET[value % FIRM_PASSWORD_ALPHABET.length]).join('');
  }

  return { generate };
}

export { useOneTimePassword };
