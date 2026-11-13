import { render, screen } from '@testing-library/vue';
import { describe, expect, it } from 'vitest';
import i18n from '@Libraries/i18n';
import { SIMULATION_FIXTURE } from '../../../test/fixtures';
import MImpactSimulator from './MImpactSimulator.vue';

/* A8 (rnc-1 F3, R14): eight stacked years, 2033 with only CBS and IBS, every figure an estimate. */
function renderSimulator(regime: string | null = 'presumido') {
  return render(MImpactSimulator, { global: { plugins: [i18n] }, props: { regime, simulation: SIMULATION_FIXTURE } });
}

describe('MImpactSimulator', () => {
  it('draws one bar per year, 2026 to 2033', () => {
    renderSimulator();

    expect(screen.getAllByTestId('sim-year').map((year) => year.dataset.year)).toEqual([
      '2026',
      '2027',
      '2028',
      '2029',
      '2030',
      '2031',
      '2032',
      '2033',
    ]);
  });

  it('stacks only CBS and IBS in 2033', () => {
    renderSimulator();

    const years = screen.getAllByTestId('sim-year');
    const year2033 = years[years.length - 1]!;

    expect([...year2033.querySelectorAll<HTMLElement>('[data-tax]')].map((rect) => rect.dataset.tax)).toEqual([
      'cbs',
      'ibs',
    ]);
  });

  it('names the five taxes in the legend and labels every figure an estimate', () => {
    renderSimulator();

    expect(screen.getByTestId('sim-legend').querySelectorAll('li')).toHaveLength(5);
    expect(screen.getByTestId('split-payment').textContent).toContain('R$ 16–28 mil/mês');
    expect(screen.getByTestId('simulator').textContent).toMatch(/estimate/i);
  });

  it('explains the Simples comparison only to a company on the Simples', () => {
    renderSimulator('simples');
    expect(screen.queryByTestId('simples-note')).toBeTruthy();
  });

  it('keeps the Simples note away from the other regimes', () => {
    renderSimulator('real');
    expect(screen.queryByTestId('simples-note')).toBeNull();
  });
});
