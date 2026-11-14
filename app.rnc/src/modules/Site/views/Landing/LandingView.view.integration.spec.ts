import { screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import AWordRise from '../../components/atoms/AWordRise/AWordRise.vue';
import MCbsCountdown from '../../components/molecules/MCbsCountdown/MCbsCountdown.vue';
import MReformaTimeline from '../../components/molecules/MReformaTimeline/MReformaTimeline.vue';
import MSectorGrid from '../../components/molecules/MSectorGrid/MSectorGrid.vue';
import { FIRM_FIXTURE, PRIME_FIRM_FIXTURE, SECTORS_FIXTURE } from '../../test/fixtures';
import LandingView from './LandingView.view.vue';

/* A3 (rnc-1 F2, R5, R8): the landing, with its content, its CTA and its own title. */
const push = vi.fn();
const sendBeacon = vi.fn<(url: string, body: Blob) => boolean>(() => true);

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: {} }),
  useRouter: () => ({ push }),
}));

const STUBS = {
  MDiagnosticPreview: true,
  RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' },
  ThePublicLayout: { template: '<div data-testid="public-layout"><slot /></div>' },
};

function renderLanding() {
  return renderWithPlugins(LandingView, {
    global: { components: { AWordRise, Button, MCbsCountdown, MReformaTimeline, MSectorGrid, Skeleton }, stubs: STUBS },
  });
}

beforeEach(() => {
  push.mockClear();
  sendBeacon.mockClear();
  vi.stubGlobal('navigator', { ...navigator, sendBeacon });
  server.use(
    http.get('http://localhost/public/firm', ({ request }) =>
      HttpResponse.json({ data: request.headers.get('X-Brand') === 'prime' ? PRIME_FIRM_FIXTURE : FIRM_FIXTURE })
    ),
    http.get('http://localhost/public/sectors', () => HttpResponse.json({ data: SECTORS_FIXTURE }))
  );
});

describe('LandingView', () => {
  it('opens with the CBS date, the question and the countdown', () => {
    renderLanding();

    expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('CBS starts being charged on 01/01/2027.');
    expect(screen.getByTestId('countdown')).toBeTruthy();
  });

  it('shows the four proof points, the five changes and the eight years of the transition', () => {
    renderLanding();

    expect(screen.getByTestId('proof').querySelectorAll('li')).toHaveLength(4);
    expect(screen.getByTestId('proof').textContent).toContain('35 anos');
    expect(screen.getByTestId('proof').textContent).toContain('ISO 9001');
    expect(screen.getByTestId('what-changes').querySelectorAll('li')).toHaveLength(5);
    expect([...screen.getByTestId('reforma-timeline').querySelectorAll('li')].map((year) => year.dataset.year)).toEqual(
      ['2026', '2027', '2028', '2029', '2030', '2031', '2032', '2033']
    );
    expect(screen.getByTestId('news').textContent).toContain('2028');
  });

  it('lists the six sector pages the API serves', async () => {
    renderLanding();

    await waitFor(() => expect(screen.getAllByTestId('sector-link')).toHaveLength(6));
    expect(screen.getAllByTestId('sector-link').map((link) => link.dataset.sector)).toEqual(
      SECTORS_FIXTURE.map((sector) => sector.slug)
    );
  });

  it('says the sectors did not load and offers to try again', async () => {
    server.use(http.get('http://localhost/public/sectors', () => HttpResponse.json({}, { status: 500 })));

    renderLanding();

    await waitFor(() => expect(screen.getByTestId('sectors-failed')).toBeTruthy());
    expect(screen.getByRole('button', { name: /try again/i })).toBeTruthy();
  });

  it('counts the visit and, on the CTA, the click before going to the diagnostic', async () => {
    renderLanding();

    screen.getByTestId('hero-cta').click();

    const types = await Promise.all(sendBeacon.mock.calls.map(async ([, body]) => JSON.parse(await body.text()).type));

    expect(types).toEqual(['visit', 'cta']);
    expect(push).toHaveBeenCalledWith({ name: 'site-diagnostic' });
  });

  it('sets its own title and description', async () => {
    renderLanding();

    await waitFor(() =>
      expect(document.title).toBe('Tax reform 2026–2033: what changes for your company · Franco Contabilidade')
    );
    expect(document.head.querySelector('meta[name="description"]')?.getAttribute('content')).toContain('01/01/2027');
  });
});

/*
 * rnc-4 verify P2-S2: from 01/01/2027 "the CBS starts being charged on 01/01/2027" is false. On the countdown's
 * own condition (the date has passed, so it shows `site.countdown.started`) the title and the description say
 * it is charged since then.
 */
describe('LandingView after 01/01/2027', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date(2027, 0, 2, 10));
  });

  afterEach(() => vi.useRealTimers());

  it('says the CBS has been charged since the date, in the title and the description', async () => {
    renderLanding();

    expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('CBS has been charged since 01/01/2027.');
    expect(screen.getByRole('heading', { level: 1 }).textContent).not.toContain('starts');
    await waitFor(() =>
      expect(document.head.querySelector('meta[name="description"]')?.getAttribute('content')).toContain(
        'has been charged since 01/01/2027'
      )
    );
  });
});

/*
 * A2 (rnc-3 F2, R3): under `?marca=prime` the hero carries Prime's tagline, the proof strip Prime's
 * four points and the title Prime's name; on the main brand the landing keeps Franco's own copy.
 */
describe('LandingView under a sub-brand', () => {
  afterEach(() => {
    window.history.replaceState({}, '', '/');
    sessionStorage.clear();
  });

  it("carries the brand's tagline, proof points and name in the title", async () => {
    window.history.replaceState({}, '', '/?marca=prime');

    renderLanding();

    await waitFor(() =>
      expect(screen.getByTestId('hero-kicker').textContent).toContain('A Franco dedicada às empresas de maior porte')
    );

    const proof = screen.getByTestId('proof');

    expect(proof.querySelectorAll('li')).toHaveLength(4);
    expect(proof.textContent).toContain('Médias e grandes');
    expect(proof.textContent).toContain('dedicado a cada conta');
    expect(proof.textContent).not.toContain('500+');
    expect(document.title).toBe('Tax reform 2026–2033: what changes for your company · Franco Prime Contabilidade');
  });

  // W38, review S1: until the brand answers, Franco's kicker and proof wait unseen, then the brand's show.
  it("hides the kicker and the proof strip while the brand's answer is on its way", async () => {
    let answer: () => void = () => undefined;
    const answered = new Promise<void>((resolve) => (answer = resolve));

    server.use(
      http.get('http://localhost/public/firm', async () => {
        await answered;

        return HttpResponse.json({ data: PRIME_FIRM_FIXTURE });
      })
    );
    // A slug this file never loaded: the firm is kept for the tab, as a later page of a visit keeps it.
    window.history.replaceState({}, '', '/?marca=nova');

    renderLanding();

    await waitFor(() => expect(screen.getByTestId('hero-kicker').classList).toContain('invisible'));
    expect(screen.getByTestId('proof').classList).toContain('invisible');

    answer();

    await waitFor(() => expect(screen.getByTestId('hero-kicker').classList).not.toContain('invisible'));
    expect(screen.getByTestId('proof').classList).not.toContain('invisible');
    expect(screen.getByTestId('hero-kicker').textContent).toContain('A Franco dedicada às empresas de maior porte');
  });

  it("keeps Franco's own kicker and proof points on the main brand", async () => {
    renderLanding();

    await waitFor(() => expect(document.title).toContain('· Franco Contabilidade'));

    expect(screen.getByTestId('hero-kicker').textContent).toContain('LC 214/2025');
    expect(screen.getByTestId('proof').textContent).toContain('500+');
  });
});
