import { describe, expect, it } from 'vitest';

/*
 * MSW answers with the FIRST handler whose path matches. `auth/users/:id` also matches
 * `auth/users/check-username` (the id is just "check-username"), so a static path registered after
 * a parameterised sibling is dead code: the default username check came back as a user with
 * `id: null` (NaN serialized) and no `available` at all, and a spec relying on the default proved nothing.
 */
describe('default MSW handlers', () => {
  it('answers the username check with its own handler, not the user-by-id one', async () => {
    const RESPONSE = await fetch('http://localhost/auth/users/check-username?username=alice');
    const BODY = await RESPONSE.json();

    expect(BODY).toEqual({ data: { available: true } });
  });

  it('still answers a user by id', async () => {
    const RESPONSE = await fetch('http://localhost/auth/users/7');
    const BODY = await RESPONSE.json();

    expect(BODY.data.id).toBe(7);
  });
});
