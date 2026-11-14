import { screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import AWordRise from '../../components/atoms/AWordRise/AWordRise.vue';
import MCbsCountdown from '../../components/molecules/MCbsCountdown/MCbsCountdown.vue';
import OProductBeta from '../../components/organisms/OProductBeta/OProductBeta.vue';
import OProductFaq from '../../components/organisms/OProductFaq/OProductFaq.vue';
import OProductHero from '../../components/organisms/OProductHero/OProductHero.vue';
import OProductHowItWorks from '../../components/organisms/OProductHowItWorks/OProductHowItWorks.vue';
import OProductModules from '../../components/organisms/OProductModules/OProductModules.vue';
import OProductUrgency from '../../components/organisms/OProductUrgency/OProductUrgency.vue';
import { SITE_ROUTE_NAMES } from '../../constants';
import { PLATFORM_FIRM_FIXTURE } from '../../test/fixtures';
import ProductLandingView from './ProductLandingView.view.vue';

/*
 * rnc-4 A32 (R28): the root's site is the product's landing for accounting firms — the product's
 * name from the API, the Reforma's dated urgency with the countdown, how the hub works for a firm, the
 * module menu, how the beta works (no price, D23), the FAQ, the beta CTA and the demo firm's link
 * (D24) — and never the diagnostic's way in.
 */
const sendBeacon = vi.fn<(url: string, body: Blob) => boolean>(() => true);

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  MBetaSignupForm: { template: '<form data-testid="beta-form-stub" />' },
  RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' },
  ThePublicLayout: { template: '<div data-testid="public-layout"><slot /></div>' },
};

function renderProductLanding() {
  return renderWithPlugins(ProductLandingView, {
    global: {
      components: {
        AWordRise,
        MCbsCountdown,
        OProductBeta,
        OProductFaq,
        OProductHero,
        OProductHowItWorks,
        OProductModules,
        OProductUrgency,
      },
      stubs: STUBS,
    },
  });
}

beforeEach(() => {
  sendBeacon.mockClear();
  vi.stubGlobal('navigator', { ...navigator, sendBeacon });
  server.use(http.get('http://localhost/public/firm', () => HttpResponse.json({ data: PLATFORM_FIRM_FIXTURE })));
});

describe('ProductLandingView', () => {
  it("names the product from the API and speaks to accounting firms, with the CBS's date and the countdown", async () => {
    renderProductLanding();

    await waitFor(() => expect(screen.getByTestId('product-name').textContent).toContain('Hub da Reforma'));
    expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('accounting firm');
    expect(screen.getByTestId('product-urgency').textContent).toContain('01/01/2027');
    expect(screen.getByTestId('countdown')).toBeTruthy();
  });

  it("dates every Reforma fact it states and names the law's source", () => {
    renderProductLanding();

    const facts = screen.getAllByTestId('product-fact');

    expect(facts.length).toBeGreaterThanOrEqual(3);
    expect(facts.map((fact) => fact.dataset.date)).toEqual(['2026', '01/01/2027', '2029–2033']);
    expect(screen.getByTestId('product-urgency').textContent).toContain('LC 214/2025');
  });

  it('walks the four steps a firm gets — diagnostic, booking, pipeline, metrics — and the module menu', () => {
    renderProductLanding();

    expect(screen.getAllByTestId('product-step').map((step) => step.dataset.step)).toEqual([
      'diagnostic',
      'booking',
      'pipeline',
      'metrics',
    ]);
    expect(screen.getAllByTestId('product-module-core')).toHaveLength(5);
    expect(screen.getAllByTestId('product-module-optin').length).toBeGreaterThanOrEqual(6);
  });

  it('explains the beta without a price and carries the form', () => {
    renderProductLanding();

    const beta = screen.getByTestId('product-beta');

    expect(beta.textContent).toContain('three months');
    expect(beta.textContent).toContain('Promotional price for the beta firms — talk to us');
    expect(beta.textContent).not.toMatch(/R\$\s?\d/);
    expect(screen.getByTestId('beta-form-stub')).toBeTruthy();
  });

  it('answers the questions a firm asks before joining', () => {
    renderProductLanding();

    expect(screen.getAllByTestId('product-faq-item').length).toBeGreaterThanOrEqual(5);
  });

  it("points the CTAs at the beta form and links the demo firm's site", () => {
    renderProductLanding();

    expect(screen.getByTestId('product-beta-cta').getAttribute('href')).toBe('#beta');
    expect(screen.getByTestId('product-closing-cta').getAttribute('href')).toBe('#beta');
    expect(screen.getByTestId('product-demo-link').getAttribute('href')).toMatch(
      /^http:\/\/alvorada\.localhost(:\d+)?\/$/
    );
  });

  it("never offers the diagnostic's way in", () => {
    const { container } = renderProductLanding();

    expect(screen.queryByTestId('hero-cta')).toBeNull();
    expect(container.innerHTML).not.toContain(SITE_ROUTE_NAMES.diagnostic);
  });

  it('counts the visit and writes its own title', async () => {
    renderProductLanding();

    expect(sendBeacon).toHaveBeenCalledTimes(1);
    await waitFor(() => expect(document.title).toContain('Reforma hub for accounting firms'));
  });
});

/*
 * rnc-4 verify P2-S2: from 01/01/2027 "your clients' questions come before that", "before 2027" and "is charged
 * from" read as a promise about a date already gone. On the countdown's own condition the urgency title, the
 * closing line and the description say the CBS is charged since then.
 */
describe('ProductLandingView after 01/01/2027', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date(2027, 0, 2, 10));
  });

  afterEach(() => vi.useRealTimers());

  it('speaks of the CBS as already charged', async () => {
    const { container } = renderProductLanding();

    expect(container.textContent).not.toContain('before 2027');
    expect(container.textContent).not.toContain('come before that');
    expect(container.textContent).toContain('The CBS has been charged since 01/01/2027.');
    await waitFor(() =>
      expect(document.head.querySelector('meta[name="description"]')?.getAttribute('content')).toContain(
        'has been charged since 01/01/2027'
      )
    );
  });
});
