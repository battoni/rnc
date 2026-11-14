import { fireEvent, screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { createPinia, setActivePinia } from 'pinia';
import Button from 'primevue/button';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { useUserStore } from '@UserModule';
import OViewAsBanner from './OViewAsBanner.vue';

/*
 * rnc-4 A43 (R26): while `me.platform.view_as` is set the back office shows a banner naming the firm
 * the operator is inside; "Sair" ends it on the API, reads the session again (no view-as), repaints the
 * root's own brand and returns to the console. Outside a view-as it draws nothing.
 */
const push = vi.fn(() => Promise.resolve());

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRouter: () => ({ push }),
}));

const OPERATOR = {
  firm: null,
  id: 1,
  last_name: 'Battoni',
  name: 'Guilherme',
  permissions: ['platform.firms'],
  role: 'Operador',
  role_slug: 'operador',
  username: 'battoni',
};

let stops = 0;
let firmReads = 0;

function renderBanner(viewAs: { name: string; slug: string } | null) {
  const pinia = createPinia();

  setActivePinia(pinia);
  useUserStore().setUserAndPermissions(
    { ...OPERATOR, platform: { is_operator: true, name: 'Hub da Reforma', view_as: viewAs } } as never,
    ['platform.firms']
  );

  return renderWithPlugins(OViewAsBanner, { global: { components: { Button } }, pinia });
}

beforeEach(() => {
  push.mockClear();
  stops = 0;
  firmReads = 0;
  server.use(
    http.delete('http://localhost/platform/view-as', () => {
      stops += 1;

      return HttpResponse.json({ data: null });
    }),
    http.get('http://localhost/auth/me', () =>
      HttpResponse.json({
        data: { ...OPERATOR, platform: { is_operator: true, name: 'Hub da Reforma', view_as: null } },
      })
    ),
    http.get('http://localhost/public/firm', () => {
      firmReads += 1;

      return HttpResponse.json({ data: { logo_text: 'Hub da Reforma', name: 'Hub da Reforma', slug: 'hub' } });
    })
  );
});

describe('OViewAsBanner', () => {
  it('draws nothing outside a view-as', () => {
    renderBanner(null);

    expect(screen.queryByTestId('view-as-banner')).toBeNull();
  });

  it('names the firm the operator is inside, as the platform operator, with "Sair"', () => {
    renderBanner({ name: 'Alvorada Contabilidade', slug: 'alvorada' });

    const banner = screen.getByTestId('view-as-banner');

    expect(banner.getAttribute('role')).toBe('status');
    expect(banner.textContent).toContain('Alvorada Contabilidade');
    expect(screen.getByTestId('view-as-leave').textContent).toContain('Leave');
  });

  it('"Sair" ends it, reads the session and the brand again, and returns to the console', async () => {
    renderBanner({ name: 'Alvorada Contabilidade', slug: 'alvorada' });

    await fireEvent.click(screen.getByTestId('view-as-leave'));

    await waitFor(() => expect(push).toHaveBeenCalledTimes(1));
    expect(stops).toBe(1);
    expect(firmReads).toBe(1);
    expect(useUserStore().platform?.view_as).toBeNull();
    expect(push).toHaveBeenCalledWith({ name: 'platform-firms-en' });
    expect(screen.queryByTestId('view-as-banner')).toBeNull();
  });
});
