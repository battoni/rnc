import { render, screen } from '@testing-library/vue';
import PrimeVue from 'primevue/config';
import { describe, expect, it } from 'vitest';
import i18n from '@Libraries/i18n';
import { LEAD_CHANNEL } from '../../../enums';
import { LEAD_CARD_FIXTURE } from '../../../test/fixtures';
import MLeadChannelChip from '../MLeadChannelChip/MLeadChannelChip.vue';
import MLeadCard from './MLeadCard.vue';

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

  it('shows no chip on a form lead', () => {
    renderCard(LEAD_CHANNEL.FORMULARIO);

    expect(screen.queryByTestId('lead-channel')).toBeNull();
  });
});
