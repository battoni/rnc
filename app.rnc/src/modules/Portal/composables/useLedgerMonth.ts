import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

/** `YYYY-MM` as its year and its month number (1–12). */
function parseMonth(month: string): [number, number] {
  const [year = 0, monthNumber = 1] = month.split('-').map(Number);

  return [year, monthNumber];
}

/** `YYYY-MM` moved by whole months: `shiftMonth('2026-01', -1)` is `'2025-12'`. */
function shiftMonth(month: string, by: number): string {
  const [year, monthNumber] = parseMonth(month);
  const index = year * 12 + (monthNumber - 1) + by;

  return `${Math.floor(index / 12)}-${String((index % 12) + 1).padStart(2, '0')}`;
}

/**
 * The month a ledger screen shows (rnc-2 R11), the portal's statement and Franco's network ledger
 * alike. The first request sends none and the API answers this month in São Paulo, so the screen
 * never decides "now" from the browser's clock; ◀ ▶ move from there, and ▶ stops at that month —
 * a later one has nothing yet.
 */
export default function useLedgerMonth() {
  const { locale } = useI18n();

  const currentMonth = ref<string | null>(null);
  const month = ref<string | null>(null);

  const isLatest = computed(() => month.value === null || month.value === currentMonth.value);

  const monthLabel = computed(() => {
    if (!month.value) return '';

    const [year, monthNumber] = parseMonth(month.value);

    const label = new Intl.DateTimeFormat(locale.value, { month: 'long', timeZone: 'UTC', year: 'numeric' }).format(
      new Date(Date.UTC(year, monthNumber - 1, 1))
    );

    return label.charAt(0).toUpperCase() + label.slice(1);
  });

  /** What the API answered: the month on screen, and the first answer is "this month". */
  function settle(answered: string) {
    month.value = answered;
    currentMonth.value ??= answered;
  }

  function shift(by: number) {
    if (month.value) month.value = shiftMonth(month.value, by);
  }

  return { isLatest, month, monthLabel, settle, shift };
}

export { shiftMonth };
