import { beforeEach, describe, expect, it, vi } from 'vitest';

const { api } = vi.hoisted(() => ({ api: vi.fn() }));

vi.mock('@Providers', () => ({ API: api }));

import getIdeas from './getIdeas.service';

/*
 * The filters travel through `query`, which drops empty values and sends a boolean as `1`/`0`
 * (ApiProvider's `buildQuery`). A hand-built query string was a second encoder to keep in step.
 */
describe('getIdeas', () => {
  beforeEach(() => api.mockReset().mockResolvedValue({ data: [] }));

  it('sends the filters as a query, not as a hand-built suffix', async () => {
    await getIdeas({ area: undefined, mine: true, search: '', sort: 'votes', status: 'planned' } as never);

    expect(api).toHaveBeenCalledWith({
      endpoint: 'ideas',
      method: 'get',
      query: { area: undefined, mine: true, search: '', sort: 'votes', status: 'planned', type: undefined },
    });
  });

  // `mine: false` means "everyone's", which is the API's default: it stays off the wire, as before.
  it('leaves mine out when it is off', async () => {
    await getIdeas({ mine: false });

    expect(api.mock.calls[0]![0].query.mine).toBeUndefined();
  });
});
