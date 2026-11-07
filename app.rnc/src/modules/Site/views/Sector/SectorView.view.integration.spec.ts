import { reactive } from 'vue';
import { screen, waitFor } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import MSectorGrid from '../../components/molecules/MSectorGrid/MSectorGrid.vue';
import { AGRO_PAGE_FIXTURE, FIRM_FIXTURE, SECTORS_FIXTURE } from '../../test/fixtures';
import SectorView from './SectorView.view.vue';

/* A3 (rnc-1 F2, R6, R8): a sector page renders its dated facts, the CTA and its own title. */
const push = vi.fn();
const sendBeacon = vi.fn<(url: string, body: Blob) => boolean>(() => true);
const route = reactive({ hash: '', params: { slug: 'agro' } });

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => route,
  useRouter: () => ({ push }),
}));

const COMPONENTS = { Button, MSectorGrid, Skeleton };

const STUBS = {
  RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' },
  ThePublicLayout: { template: '<div data-testid="public-layout"><slot /></div>' },
};

async function sentEvents() {
  return Promise.all(sendBeacon.mock.calls.map(async ([, body]) => JSON.parse(await body.text())));
}

beforeEach(() => {
  push.mockClear();
  sendBeacon.mockClear();
  route.params.slug = 'agro';
  vi.stubGlobal('navigator', { ...navigator, sendBeacon });
  server.use(
    http.get('http://localhost/public/firm', () => HttpResponse.json({ data: FIRM_FIXTURE })),
    http.get('http://localhost/public/sectors', () => HttpResponse.json({ data: SECTORS_FIXTURE })),
    http.get('http://localhost/public/sectors/agro', () => HttpResponse.json({ data: AGRO_PAGE_FIXTURE })),
    http.get('http://localhost/public/sectors/mineracao', () =>
      HttpResponse.json({ error: 'content.sectorPage.notFound' }, { status: 404 })
    )
  );
});

describe('SectorView', () => {
  it('renders the title, the lede and every dated fact with its source', async () => {
    renderWithPlugins(SectorView, { global: { components: COMPONENTS, stubs: STUBS } });

    await waitFor(() =>
      expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('Reforma Tributária para o agro')
    );
    expect(screen.getByText('Muitos itens com alíquota zero.')).toBeTruthy();

    const facts = screen.getAllByTestId('sector-fact');

    expect(facts).toHaveLength(3);
    expect(facts[0]!.textContent).toContain('2026');
    expect(facts[0]!.querySelector('a')?.getAttribute('href')).toBe(
      'https://www.planalto.gov.br/ccivil_03/leis/lcp/lcp214.htm'
    );
  });

  it('sets the title and description the API wrote for the page', async () => {
    renderWithPlugins(SectorView, { global: { components: COMPONENTS, stubs: STUBS } });

    await waitFor(() => expect(document.title).toBe('Reforma Tributária no agro · Franco Contabilidade'));
    expect(document.head.querySelector('meta[property="og:description"]')?.getAttribute('content')).toBe(
      'O que muda no agro.'
    );
  });

  it('links the other five sectors', async () => {
    renderWithPlugins(SectorView, { global: { components: COMPONENTS, stubs: STUBS } });

    await waitFor(() => expect(screen.getAllByTestId('sector-link')).toHaveLength(5));
    expect(screen.getAllByTestId('sector-link').map((link) => link.dataset.sector)).not.toContain('agro');
  });

  it('counts the visit with its sector, and the CTA click before the diagnostic', async () => {
    renderWithPlugins(SectorView, { global: { components: COMPONENTS, stubs: STUBS } });

    await waitFor(() => expect(screen.getByTestId('sector-cta')).toBeTruthy());
    screen.getByTestId('sector-cta').click();

    expect((await sentEvents()).map((event) => [event.type, event.sector])).toEqual([
      ['visit', 'agro'],
      ['cta', 'agro'],
    ]);
    expect(push).toHaveBeenCalledWith({ name: 'site-diagnostic', query: { setor: 'agro' } });
  });

  it('says a sector that does not exist does not exist, and counts no visit', async () => {
    route.params.slug = 'mineracao';

    renderWithPlugins(SectorView, { global: { components: COMPONENTS, stubs: STUBS } });

    await waitFor(() => expect(screen.getByTestId('sector-missing')).toBeTruthy());
    expect(screen.queryByTestId('sector-cta')).toBeNull();
    expect(sendBeacon).not.toHaveBeenCalled();
  });

  it('loads the next sector when the address changes in place', async () => {
    server.use(
      http.get('http://localhost/public/sectors/saude', () =>
        HttpResponse.json({ data: { ...AGRO_PAGE_FIXTURE, slug: 'saude', title: 'Reforma Tributária para a saúde' } })
      )
    );

    renderWithPlugins(SectorView, { global: { components: COMPONENTS, stubs: STUBS } });
    await waitFor(() => expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('agro'));

    route.params.slug = 'saude';

    await waitFor(() => expect(screen.getByRole('heading', { level: 1 }).textContent).toContain('saúde'));
  });
});
