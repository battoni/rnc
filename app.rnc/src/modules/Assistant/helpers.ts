import { ASSISTANT_TIME_ZONE } from './constants';

/*
 * The assistant's screens write dates the way Franco reads them, dd/mm/yyyy, in both languages — as
 * the channel's screen does — and count days in São Paulo, as the API does (W52).
 */
const DAY_FORMAT = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit',
  month: '2-digit',
  timeZone: ASSISTANT_TIME_ZONE,
  year: 'numeric',
});

const SAFE_URL = /^https?:\/\//i;

/** An instant (`2026-11-05T13:00:00+00:00`) as its São Paulo day. */
function formatInstantDay(iso: string): string {
  return DAY_FORMAT.format(new Date(iso));
}

/** A day the API sends as `YYYY-MM-DD` (a source's `as_of`), with no time-zone shift. */
function formatPlainDay(day: string): string {
  const [year, month, date] = day.split('-');

  return `${date}/${month}/${year}`;
}

/** Today in São Paulo as `YYYY-MM-DD` — the note form's default "lida em" and its upper bound. */
function todayInSaoPaulo(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: ASSISTANT_TIME_ZONE }).format(new Date());
}

/** The address only when it is http(s): a note's source is typed by a person, and a link runs it. */
function safeUrl(url: string): string | undefined {
  return SAFE_URL.test(url.trim()) ? url.trim() : undefined;
}

export { formatInstantDay, formatPlainDay, safeUrl, todayInSaoPaulo };
