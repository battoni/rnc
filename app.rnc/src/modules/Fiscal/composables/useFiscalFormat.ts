import { useI18n } from 'vue-i18n';
import type { CentsRange } from '../interfaces';
import { formatReal } from '@Helpers';

const CENTS_PER_REAL = 100;

/**
 * The fiscal screens' figures in the screen's language: counts, percentages to one decimal, a
 * money range in full ("R$ 77.400,00 – R$ 84.600,00") or short for a headline that must fit a card
 * at 390 px ("R$ 1,2 mi – R$ 1,3 mi", the demo's `fMoneyShort`), and a moment in São Paulo.
 */
export default function useFiscalFormat() {
  const { locale } = useI18n();

  function formatCount(value: number): string {
    return new Intl.NumberFormat(locale.value).format(value);
  }

  function formatPercent(value: number | null): string {
    if (value === null) return '—';

    return `${new Intl.NumberFormat(locale.value, { maximumFractionDigits: 1 }).format(value)}%`;
  }

  function formatRange([low, high]: CentsRange): string {
    return `${formatReal(low)} – ${formatReal(high)}`;
  }

  function formatShortReal(cents: number): string {
    return new Intl.NumberFormat('pt-BR', {
      currency: 'BRL',
      maximumFractionDigits: 1,
      notation: 'compact',
      style: 'currency',
    }).format(cents / CENTS_PER_REAL);
  }

  function formatShortRange([low, high]: CentsRange): string {
    return `${formatShortReal(low)} – ${formatShortReal(high)}`;
  }

  function formatMoment(at: string): string {
    return new Intl.DateTimeFormat('pt-BR', {
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      month: '2-digit',
      timeZone: 'America/Sao_Paulo',
      year: 'numeric',
    }).format(new Date(at));
  }

  return { formatCount, formatMoment, formatPercent, formatRange, formatShortRange, formatShortReal };
}
