import { screen, within } from '@testing-library/vue';
import Button from 'primevue/button';
import Skeleton from 'primevue/skeleton';
import { describe, expect, it } from 'vitest';
import type { RadarTypeDetail } from '../../../interfaces';
import { renderWithPlugins } from '@/test/render';
import { CONTRATOS_DETAIL, SPLIT_DETAIL } from '../../../test/fixtures';
import MRadarTypeDetail from './MRadarTypeDetail.vue';

/*
 * One Radar row opened (rnc-2 F10, R25, R28): the rule's text and its clients, biggest first, each
 * with its range as an estimate and its proof — what the XMLs showed, with up to three access keys,
 * or "do cadastro" — and "Sem autorização" on a client whose consent is not given (null included,
 * W98), which no batch takes.
 */
function renderDetail(detail: RadarTypeDetail | null, extra: Record<string, unknown> = {}) {
  return renderWithPlugins(MRadarTypeDetail, {
    props: { detail, failed: false, loading: false, ...extra },
    global: { components: { Button, Skeleton } },
  });
}

function clientOf(id: number): HTMLElement {
  return screen.getAllByTestId('radar-client').find((row) => row.getAttribute('data-id') === String(id))!;
}

describe('MRadarTypeDetail', () => {
  it('lists the clients in the API order with their range and the rule text', () => {
    renderDetail(CONTRATOS_DETAIL);

    expect(screen.getByText(CONTRATOS_DETAIL.type.detail)).toBeTruthy();
    expect(screen.getAllByTestId('radar-client').map((row) => row.getAttribute('data-id'))).toEqual(['31', '32']);
    expect(within(clientOf(31)).getByTestId('radar-client-range').textContent).toMatch(/18\.000,00.*54\.000,00/);
    expect(clientOf(31).textContent).toContain('a year');
  });

  it('R25: says what the XMLs measured, with the access keys', () => {
    renderDetail(CONTRATOS_DETAIL);

    const row = clientOf(31);

    expect(row.getAttribute('data-source')).toBe('notas');
    expect(within(row).getByTestId('radar-proof').textContent).toContain('80%');
    expect(within(row).getByTestId('radar-proof').textContent).toContain('12 invoices');
    expect(within(row).getByTestId('radar-proof').textContent).toContain('20%');
    expect(
      within(row)
        .getAllByTestId('radar-key')
        .map((key) => key.textContent?.trim())
    ).toEqual(CONTRATOS_DETAIL.clients[0]!.keys);
  });

  it('R25: an item without XML evidence says "do cadastro" and lists no key', () => {
    renderDetail(CONTRATOS_DETAIL);

    const row = clientOf(32);

    expect(within(row).getByTestId('radar-proof').textContent?.trim()).toBe('from the client record');
    expect(within(row).queryAllByTestId('radar-key')).toHaveLength(0);
  });

  it('R28: marks a client without consent — null counts as no (W98)', () => {
    renderDetail(CONTRATOS_DETAIL);

    expect(within(clientOf(31)).queryByTestId('radar-client-no-consent')).toBeNull();
    expect(within(clientOf(32)).getByTestId('radar-client-no-consent').textContent).toContain('No consent');
  });

  it('R26: the split payment reads its held cash per month from the invoices', () => {
    renderDetail(SPLIT_DETAIL);

    const row = clientOf(41);

    expect(within(row).getByTestId('radar-proof').textContent).toMatch(/100\.000,00 a month/);
    expect(within(row).getByTestId('radar-proof').textContent).toMatch(/10\.600,00.*15\.900,00 a month/);
    expect(row.textContent).toContain('a month');
  });

  it('says the clients did not load, and offers to try again', () => {
    const { emitted } = renderDetail(null, { failed: true });

    screen.getByRole('button').click();

    expect(screen.getByRole('alert')).toBeTruthy();
    expect(emitted('onRetry')).toHaveLength(1);
  });
});
