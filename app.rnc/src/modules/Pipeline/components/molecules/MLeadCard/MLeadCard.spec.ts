import Button from 'primevue/button';
import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import { LEAD_CARD_FIXTURE } from '../../../test/fixtures';
import MLeadCard from './MLeadCard.vue';

/*
 * A4, the SPA half (rnc-3 F2, R4): a lead started under a sub-brand wears its chip on the board, so
 * the team knows which face of Franco the visitor saw; a main-brand lead wears none.
 */
const RouterLinkStub = { props: ['to'], template: '<a><slot /></a>' };

function mountCard(lead = LEAD_CARD_FIXTURE) {
  return mountWithPlugins(MLeadCard, {
    props: { canUpdate: false, lead },
    global: { components: { Button }, stubs: { RouterLink: RouterLinkStub } },
  });
}

describe('MLeadCard', () => {
  it("wears the sub-brand's chip with its short name", () => {
    const chip = mountCard({ ...LEAD_CARD_FIXTURE, brand: { id: 2, short_name: 'Franco Prime' } }).find(
      '[data-testid="lead-brand"]'
    );

    expect(chip.exists()).toBe(true);
    expect(chip.text()).toBe('Franco Prime');
    expect(chip.attributes('title')).toBe('Franco Prime lead');
  });

  it('wears no chip for a main-brand lead', () => {
    expect(mountCard().find('[data-testid="lead-brand"]').exists()).toBe(false);
  });
});
