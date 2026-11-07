/**
 * A calendar day, or a wall-clock moment, written the way the API reads it — in the person's own
 * time zone.
 *
 * `toISOString()` answers in UTC. In São Paulo (UTC−3) that is already TOMORROW from 21:00 on, so a
 * birth date picked at night, or a "today" filter used after dinner, reached the API one day late —
 * and nothing failed, because a wrong date is still a valid date. A day the person chose is a local
 * fact, so it is serialized with the local getters.
 */

function pad(value: number): string {
  return String(value).padStart(2, '0');
}

/** `YYYY-MM-DD` for the day the person sees on their own calendar. */
function toLocalDateString(date: Date): string {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

/** `YYYY-MM-DDTHH:mm:ss` on the person's own clock, with no offset — a local date-time. */
function toLocalDateTimeString(date: Date): string {
  const TIME = `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;

  return `${toLocalDateString(date)}T${TIME}`;
}

/**
 * A `dd/mm/yyyy` string as a local date, or `null` when it does not name a real day. `Date` rolls
 * 31/02 over into March without complaint; a date the person did not type is refused here instead.
 */
function fromDayMonthYear(value: string): Date | null {
  const MATCH = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(value.trim());

  if (!MATCH) return null;

  const [, day, month, year] = MATCH.map(Number);
  const DATE = new Date(year!, month! - 1, day);
  const IS_SAME_DAY = DATE.getFullYear() === year && DATE.getMonth() === month! - 1 && DATE.getDate() === day;

  return IS_SAME_DAY ? DATE : null;
}

export { fromDayMonthYear, toLocalDateString, toLocalDateTimeString };
