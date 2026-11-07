import { useI18n } from 'vue-i18n';

/**
 * Timestamps for the tip's audit trail, in the language the interface is speaking.
 *
 * The origin project hard-coded `pt-BR` here; a bootstrap cannot. Neither can it read the
 * browser's locale — that is the machine's language, not the app's, and it would print a
 * Portuguese screen with American dates. `locale` from i18n is the one that follows what the
 * reader was actually shown.
 */
export default function useTipFormat() {
  const { locale } = useI18n();

  // HELPERS

  /** Audit trails read as "when, then who" — a date without the time is not enough to order them. */
  function formatDateTime(raw: unknown): string {
    if (!raw) return '—';

    const value = new Date(String(raw));

    if (Number.isNaN(value.getTime())) return '—';

    return new Intl.DateTimeFormat(locale.value, { dateStyle: 'short', timeStyle: 'short' }).format(value);
  }

  return { formatDateTime };
}
