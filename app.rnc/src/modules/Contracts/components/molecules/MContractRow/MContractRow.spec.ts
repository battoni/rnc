import { screen } from '@testing-library/vue';
import Button from 'primevue/button';
import { describe, expect, it } from 'vitest';
import type { ContractAlertRow } from '../../../interfaces';
import { renderWithPlugins } from '@/test/render';
import { ANDRADE, BANDEIRA, REFUSED } from '../../../test/fixtures';
import MContractRow from './MContractRow.vue';

/*
 * One at-risk contract (rnc-3 R19): the years it is exposed are named for a screen reader (the chips
 * are drawn, `aria-hidden`), and a contract starting after 2033 — still listed at exposure 0 (W80) —
 * lights no chip and says it is outside the transition, its bar at the 2 % floor.
 */
const SELECT_STUB = { props: ['inputId', 'modelValue', 'options'], template: '<select :data-testid="inputId" />' };

function renderRow(contract: ContractAlertRow) {
  return renderWithPlugins(MContractRow, {
    global: { components: { Button }, stubs: { Select: SELECT_STUB } },
    props: { contract, partners: [ANDRADE], topExposureCents: BANDEIRA.exposure_cents },
  });
}

describe('MContractRow', () => {
  it('names the exposed years for a screen reader', () => {
    renderRow(REFUSED);

    expect(screen.getByRole('list', { name: 'Exposed from 2028 to 2029' })).toBeTruthy();
    expect(screen.getByText('2 years exposed')).toBeTruthy();
  });

  it('lights no chip for a contract that starts after 2033, and says so', () => {
    renderRow({ ...BANDEIRA, end_year: 2036, exposure_cents: 0, start_year: 2034, years_exposed: 0 });

    expect(screen.getAllByTestId('contract-year').filter((chip) => chip.dataset.lit === 'true')).toEqual([]);
    expect(screen.getByRole('list', { name: 'Outside the 2027 to 2033 transition' })).toBeTruthy();
    expect((screen.getByTestId('contract-bar') as HTMLElement).style.width).toBe('2%');
  });
});
