import { screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import MCbsCountdown from '../../components/molecules/MCbsCountdown/MCbsCountdown.vue';
import MReformaTimeline from '../../components/molecules/MReformaTimeline/MReformaTimeline.vue';
import MSectorGrid from '../../components/molecules/MSectorGrid/MSectorGrid.vue';
import { FIRM_FIXTURE, SECTORS_FIXTURE } from '../../test/fixtures';
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
    global: { components: { Button, MCbsCountdown, MReformaTimeline, MSectorGrid, Skeleton }, stubs: STUBS },
  });
}

beforeEach(() => {
  push.mockClear();
  sendBeacon.mockClear();
  vi.stubGlobal('navigator', { ...navigator, sendBeacon });
  server.use(
    http.get('http://localhost/public/firm', () => HttpResponse.json({ data: FIRM_FIXTURE })),
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
