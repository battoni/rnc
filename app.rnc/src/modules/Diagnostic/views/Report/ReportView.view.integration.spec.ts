import { fireEvent, screen } from '@testing-library/vue';
import { http, HttpResponse } from 'msw';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { server } from '@/test/msw/server';
import { renderWithPlugins } from '@/test/render';
import MDiagnosticResult from '../../components/molecules/MDiagnosticResult/MDiagnosticResult.vue';
import MImpactSimulator from '../../components/molecules/MImpactSimulator/MImpactSimulator.vue';
import { REPORT_FIXTURE, REPORT_TOKEN_FIXTURE } from '../../test/fixtures';
import ReportView from './ReportView.view.vue';

/*
 * The "Relatório de Impacto" (rnc-1 F4, R17, AM4, AM15) at `/relatorio/:token`: the company, the
 * locked result, the simulator and the meeting, printable; a dead link is a calm message.
 */
const routeParams: { token: string } = { token: REPORT_TOKEN_FIXTURE };

vi.mock('vue-router', async (importOriginal) => ({
  ...(await importOriginal<object>()),
  useRoute: () => ({ hash: '', params: routeParams, query: {} }),
  useRouter: () => ({ push: vi.fn() }),
}));

const STUBS = {
  RouterLink: { props: ['to'], template: '<a :data-to="JSON.stringify(to)"><slot /></a>' },
  ThePublicLayout: { template: '<div data-testid="public-layout"><slot /></div>' },
};

function renderReport() {
  return renderWithPlugins(ReportView, {
    global: { components: { Button, MDiagnosticResult, MImpactSimulator, Skeleton }, stubs: STUBS },
  });
}

beforeEach(() => {
  routeParams.token = REPORT_TOKEN_FIXTURE;
  server.use(
    http.get('http://localhost/public/firm', () =>
      HttpResponse.json({
        data: { address: 'Av. Corifeu de Azevedo Marques, 2333', name: 'Franco Contabilidade', phone: '11 3732-5500' },
      })
    ),
    http.get(`http://localhost/public/reports/${REPORT_TOKEN_FIXTURE}`, () =>
      HttpResponse.json({ data: REPORT_FIXTURE })
    )
  );
});

describe('ReportView', () => {
  it('shows the company, the locked result, the simulator and the meeting', async () => {
    renderReport();

    const report = await screen.findByTestId('impact-report');

    expect(screen.getByTestId('report-company').textContent).toContain('Clínica Horizonte');
    expect(screen.getAllByTestId('result-item')).toHaveLength(5);
    expect(screen.getAllByTestId('locked')).toHaveLength(5);
    expect(screen.getAllByTestId('sim-year')).toHaveLength(8);
    expect(screen.getByTestId('report-meeting').textContent).toContain('Juliana Reis');
    expect(screen.getByTestId('report-meeting').textContent).toContain('14:30');
    expect(report.textContent).toContain('Franco Contabilidade');
  });

  it('prints the page', async () => {
    const print = vi.spyOn(window, 'print').mockImplementation(() => undefined);

    renderReport();
    await screen.findByTestId('impact-report');

    await fireEvent.click(screen.getByTestId('report-print'));

    expect(print).toHaveBeenCalledOnce();
  });

  it('says calmly that the link expired when the API does not know it', async () => {
    routeParams.token = 'x'.repeat(48);
    server.use(
      http.get('http://localhost/public/reports/:token', () =>
        HttpResponse.json({ message: 'booking.reportNotFound' }, { status: 404 })
      )
    );

    renderReport();

    expect((await screen.findByTestId('report-missing')).textContent).toMatch(/expired|not found/i);
    expect(screen.queryByTestId('impact-report')).toBeNull();
  });
});
