import { useI18n } from 'vue-i18n';
import { toLocalDateString, toSentenceCaseHelper } from '@Helpers';

/**
 * Dates on the board, in the reader's language.
 *
 * Timestamps arrive as ISO-8601 from the API — real instants, unlike a plain `YYYY-MM-DD` day,
 * which has no time zone and must never go through `new Date(day)` — so `new Date(iso)` is correct
 * here and the string parsing a calendar day needs is not.
 */
export default function useIdeaFormat() {
  const { locale } = useI18n();

  // HELPERS

  function formatMoment(iso: string | null): string {
    if (!iso) return '';

    return new Intl.DateTimeFormat(locale.value, {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    }).format(new Date(iso));
  }

  /** The heading Novidades groups entries under. */
  function formatMonth(iso: string | null): string {
    if (!iso) return '';

    return toSentenceCaseHelper(
      new Intl.DateTimeFormat(locale.value, { month: 'long', year: 'numeric' }).format(new Date(iso))
    );
  }

  /**
   * A stable key for that grouping — never the localised label, which changes with the locale.
   *
   * Cut on the reader's clock, like the label beside it. The ISO string is in UTC, so slicing it
   * put an entry from 30 September at 22:00 in São Paulo under October's key and September's label.
   */
  function monthKey(iso: string | null): string {
    if (!iso) return '';

    return toLocalDateString(new Date(iso)).slice(0, 7);
  }

  return { formatMoment, formatMonth, monthKey };
}
