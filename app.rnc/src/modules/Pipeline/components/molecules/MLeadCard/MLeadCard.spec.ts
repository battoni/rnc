import { render, screen } from '@testing-library/vue';
import Button from 'primevue/button';
import PrimeVue from 'primevue/config';
import { describe, expect, it } from 'vitest';
import { mountWithPlugins } from '@/test/mount';
import i18n from '@Libraries/i18n';
import { LEAD_CHANNEL } from '../../../enums';
import { LEAD_CARD_FIXTURE } from '../../../test/fixtures';
import MLeadChannelChip from '../MLeadChannelChip/MLeadChannelChip.vue';
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

/*
 * A board card's channel (rnc-2 F5, R14, A12's Vitest half): a lead that came through the chat
 * receptionist — or a portfolio client Franco referred — says so on its card; a form lead, the
 * usual case, carries no chip, so the board does not repeat "Formulário" on every card.
 */
function renderCard(channel: LEAD_CHANNEL) {
  return render(MLeadCard, {
    global: {
      components: { MLeadChannelChip },
      plugins: [i18n, PrimeVue],
      stubs: { RouterLink: { template: '<a><slot /></a>' } },
    },
    props: { canUpdate: false, lead: { ...LEAD_CARD_FIXTURE, channel } },
  });
}

describe('MLeadCard — the channel chip (R14, A12)', () => {
  it('shows "Chat" on a lead the chat receptionist started', () => {
    renderCard(LEAD_CHANNEL.CHAT);

    const chip = screen.getByTestId('lead-channel');

    expect(chip.getAttribute('data-channel')).toBe('chat');
    expect(chip.textContent?.trim()).toBe('Chat');
    expect(chip.getAttribute('title')).toBe('Channel: Chat');
  });

  it('shows the portfolio on a lead Franco referred from its own clients', () => {
    renderCard(LEAD_CHANNEL.CARTEIRA);

    expect(screen.getByTestId('lead-channel').textContent?.trim()).toBe('Portfolio');
  });

  it('shows "Beta" on a firm that signed up for the beta on the root site (rnc-4 A33)', () => {
    renderCard(LEAD_CHANNEL.BETA);

    const chip = screen.getByTestId('lead-channel');

    expect(chip.getAttribute('data-channel')).toBe('beta');
    expect(chip.textContent?.trim()).toBe('Beta');
    expect(chip.getAttribute('title')).toBe('Channel: Beta');
  });

  it('shows no chip on a form lead', () => {
    renderCard(LEAD_CHANNEL.FORMULARIO);

    expect(screen.queryByTestId('lead-channel')).toBeNull();
  });
});
