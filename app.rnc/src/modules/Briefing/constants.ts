/** The screen this module owns, linked by name (celer-05): the rail and Reinaldo's landing link here. */
const BRIEFING_ROUTE_NAMES = { briefing: 'briefing' } as const;

/** The key (rnc-3 R23, R28): the briefing is Reinaldo's (gestao; the operator holds every key). */
const BRIEFING_PERMISSIONS = { read: 'briefing.read' } as const;

/**
 * 01/01/2027 00:00 in São Paulo (R26; facts.md §2, Timeline): the CBS is charged in full. Written
 * with its offset — Brazil has had no daylight saving since 2019 — so a browser in another zone
 * counts to the same instant. It stays on this date despite the request to delay the CBS to 2028:
 * that is news "em discussão", not the law (the caption says so).
 */
const CBS_START = new Date('2027-01-01T00:00:00-03:00');

/** The zone the greeting, the date and the meeting times are read in (Reinaldo's, the API's). */
const BRIEFING_TIME_ZONE = 'America/Sao_Paulo';

/** "Bom dia" from 05:00, "Boa tarde" from 12:00, "Boa noite" from 18:00 until 04:59 (R26, W105). */
const GREETING_HOURS = { afternoon: 12, morning: 5, night: 18 } as const;

export { BRIEFING_PERMISSIONS, BRIEFING_ROUTE_NAMES, BRIEFING_TIME_ZONE, CBS_START, GREETING_HOURS };
