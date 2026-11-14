import { screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import { FIRM_FIXTURE, PLATFORM_PRIVACY_FIXTURE, PRIVACY_FIXTURE } from '../../test/fixtures';
import PrivacyView from './PrivacyView.view.vue';

/*
 * rnc-4 A47 (R38, F9b): `/privacidade` words the notice from `GET public/privacy` — the firm as the
 * controller with its name and contact, the platform as the operator, every collected item and
 * retention rule from its stable key. At the root the platform is the controller and no operator line shows.
 */
vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {}, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const COMPONENTS = { Button, Skeleton };

const STUBS = {
  RouterLink: { props: ['to'], template: '<a><slot /></a>' },
  ThePublicLayout: { template: '<div data-testid="public-layout"><slot /></div>' },
};

let privacyCalls = 0;

function answerPrivacy(body: unknown, status = 200) {
  server.use(
    http.get('http://localhost/public/privacy', () => {
      privacyCalls += 1;

      return HttpResponse.json(body as never, { status });
    })
  );
}

function renderPrivacy() {
  return renderWithPlugins(PrivacyView, { global: { components: COMPONENTS, stubs: STUBS } });
}

beforeEach(() => {
  privacyCalls = 0;
  server.use(http.get('http://localhost/public/firm', () => HttpResponse.json({ data: FIRM_FIXTURE })));
});

describe('PrivacyView', () => {
  it("renders the template with the firm's name as controller, the platform as operator and the firm's contact", async () => {
    answerPrivacy({ data: PRIVACY_FIXTURE });
    renderPrivacy();

    const controller = await screen.findByTestId('privacy-controller');

    expect(controller.textContent).toContain('Alvorada Contábil');
    expect(controller.textContent).toContain('Rua das Palmeiras, 120, Centro, Campinas-SP');
    expect(screen.getByTestId('privacy-operator').textContent).toContain('Hub da Reforma');

    const requests = screen.getByTestId('privacy-requests');

    expect(requests.querySelector('a[href="mailto:contato@alvorada.exemplo.com.br"]')).toBeTruthy();
    expect(requests.textContent).toContain('19 3200-1100');
    expect(privacyCalls).toBe(1);
  });

  it('words every collected item and retention rule from its key, never the raw key', async () => {
    answerPrivacy({ data: PRIVACY_FIXTURE });
    renderPrivacy();

    const collected = await screen.findByTestId('privacy-collected');
    const retention = screen.getByTestId('privacy-retention');

    expect(collected.querySelectorAll('li')).toHaveLength(9);
    expect(retention.querySelectorAll('li')).toHaveLength(3);

    // A missing key renders as its path (`site.privacy.collected.items.cnpj`) or as the bare key.
    for (const item of [...collected.querySelectorAll('li'), ...retention.querySelectorAll('li')]) {
      expect(item.textContent).not.toContain('site.privacy');
      expect(item.textContent?.trim()).not.toMatch(/^[a-z_]+$/);
    }

    expect(retention.textContent).not.toMatch(/until_request_or_firm_exit|kept_as_proof|pseudonymous|lead_data/);
    expect(screen.getByTestId('privacy-sharing')).toBeTruthy();
  });

  it('at the root names the platform as controller, with no operator line and no sharing', async () => {
    answerPrivacy({ data: PLATFORM_PRIVACY_FIXTURE });
    renderPrivacy();

    expect((await screen.findByTestId('privacy-controller')).textContent).toContain('Hub da Reforma');
    expect(screen.queryByTestId('privacy-operator')).toBeNull();
    expect(screen.queryByTestId('privacy-sharing')).toBeNull();
  });

  it('says the notice did not load and offers to try again', async () => {
    answerPrivacy({ error: 'boom' }, 500);
    renderPrivacy();

    const retry = await screen.findByTestId('privacy-retry');

    answerPrivacy({ data: PRIVACY_FIXTURE });
    retry.click();

    await waitFor(() => expect(screen.getByTestId('privacy-controller')).toBeTruthy());
  });
});
