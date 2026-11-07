import { screen } from '@testing-library/vue';
import { describe, expect, it } from 'vitest';
import { renderWithPlugins } from '@/test/render';
import { SLA_STATE } from '../../../enums';
import MSlaChip from './MSlaChip.vue';

/*
 * The first-contact chip (rnc-2 R8, the demo's `slaChip`): one sentence per state with the business
 * hours the API counted, against the 24-hour target, and a tone that says how it is going. Shared:
 * the portal's cards, the lead's routing panel and the trail all wear it.
 */
function renderChip(state: SLA_STATE, hours: number) {
  return renderWithPlugins(MSlaChip, { props: { sla: { hours, state } } });
}

describe('MSlaChip', () => {
  it.each([
    [SLA_STATE.OK, 3, 'First contact: 3 of 24 business hours', 'success'],
    [SLA_STATE.ATENCAO, 12.5, 'Attention: 12.5 of 24 business hours without contact', 'warn'],
    [SLA_STATE.ESTOURADO, 30, 'SLA breached: 30 business hours without contact (target 24)', 'danger'],
    [SLA_STATE.CUMPRIDO, 20, 'First contact in 20 business hours · within the 24-hour SLA', 'success'],
    [SLA_STATE.FORA, 30, 'First contact in 30 business hours · outside the 24-hour SLA', 'danger'],
    [SLA_STATE.ENCERRADO, 0, 'Declined: no SLA', 'muted'],
  ])('reads %s as its sentence and tone', (state, hours, sentence, tone) => {
    renderChip(state, hours);

    const chip = screen.getByTestId('sla-chip');

    expect(chip.textContent?.trim()).toBe(sentence);
    expect(chip.dataset.sla).toBe(state);
    expect(chip.dataset.tone).toBe(tone);
  });
});
