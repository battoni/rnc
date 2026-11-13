import { useI18n } from 'vue-i18n';
import { MEETING_TIME_ZONE } from '../constants';

/** Noon on a `YYYY-MM-DD` day in São Paulo: a moment that is that day in every zone's reading. */
const DAY_ANCHOR = 'T12:00:00-03:00';

/**
 * Meeting times as the visitor reads them (rnc-1 R15, plan AM9): always in São Paulo time — the
 * agenda's — whatever zone the browser is in, in the page's language.
 */
function useMeetingTime() {
  const { locale, t } = useI18n();

  // HELPERS
  function format(iso: string, options: Intl.DateTimeFormatOptions): string {
    return new Intl.DateTimeFormat(locale.value, { timeZone: MEETING_TIME_ZONE, ...options }).format(new Date(iso));
  }

  function dayMoment(date: string): string {
    return `${date}${DAY_ANCHOR}`;
  }

  /** "14:30", never "2:30 PM". */
  function formatTime(iso: string): string {
    return format(iso, { hour: '2-digit', hourCycle: 'h23', minute: '2-digit' });
  }

  /** "24/11" (pt-BR) for a day of the picker. */
  function formatDay(date: string): string {
    return format(dayMoment(date), { day: '2-digit', month: '2-digit' });
  }

  /** "ter" for a day of the picker. */
  function formatWeekday(date: string): string {
    return format(dayMoment(date), { weekday: 'short' }).replace('.', '');
  }

  /** "terça-feira, 24/11 às 14:30" — or "ter, 24/11 às 14:30" where the line is short (a button). */
  function formatMeeting(iso: string, weekday: 'long' | 'short' = 'long'): string {
    return t('diagnostic.booking.meetingLabel', {
      day: format(iso, { day: '2-digit', month: '2-digit' }),
      time: formatTime(iso),
      weekday: format(iso, { weekday }).replace('.', ''),
    });
  }

  return { formatDay, formatMeeting, formatTime, formatWeekday };
}

export { useMeetingTime };
