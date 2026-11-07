/*
 * PrimeVue keeps its OWN locale catalogue and never reads vue-i18n, so every widget that writes its
 * own dates — the DatePicker above all — rendered "August 2026 / Su Mo Tu" inside a pt-BR app.
 *
 * Day and month names are DERIVED from `Intl` for whichever locale the app runs in, never
 * transcribed: a new language needs no table here, and the names cannot drift from the dates the
 * rest of the app formats through `Intl`. Only the widget's own words are listed per language.
 *
 * `firstDayOfWeek` is left at PrimeVue's default (Sunday). Where a week starts is a calendar
 * decision, not a translation, and a project that decides otherwise sets it here.
 */

/** A week that starts on a Sunday, read in UTC so a timezone can never shift a name by a day. */
const REFERENCE_SUNDAY_UTC = Date.UTC(2023, 0, 1);
const MILLISECONDS_PER_DAY = 86_400_000;

/**
 * The widget's own chrome, per language. Keyed by the language subtag, so `pt-BR` and `pt-PT` both
 * land on `pt`; anything unlisted keeps English, the same fallback vue-i18n uses.
 *
 * `aria` holds the labels a screen reader announces and a pointer shows on hover: the paginator's
 * arrows, the X of every dialog (`MMainDialog`). Only the keys a widget this app renders reads are
 * listed; every other PrimeVue default stays as it ships.
 */
interface WidgetWords {
  aria: {
    close: string;
    firstPageLabel: string;
    lastPageLabel: string;
    next: string;
    nextPageLabel: string;
    pageLabel: string;
    prevPageLabel: string;
    previous: string;
    rowsPerPageLabel: string;
  };
  chooseDate: string;
  chooseMonth: string;
  chooseYear: string;
  clear: string;
  nextDecade: string;
  nextMonth: string;
  nextYear: string;
  prevDecade: string;
  prevMonth: string;
  prevYear: string;
  today: string;
  weekHeader: string;
}

const WIDGET_WORDS: Record<string, WidgetWords> = {
  en: {
    aria: {
      close: 'Close',
      firstPageLabel: 'First Page',
      lastPageLabel: 'Last Page',
      next: 'Next',
      nextPageLabel: 'Next Page',
      pageLabel: 'Page {page}',
      prevPageLabel: 'Previous Page',
      previous: 'Previous',
      rowsPerPageLabel: 'Rows per page',
    },
    chooseDate: 'Choose date',
    chooseMonth: 'Choose Month',
    chooseYear: 'Choose Year',
    clear: 'Clear',
    nextDecade: 'Next Decade',
    nextMonth: 'Next Month',
    nextYear: 'Next Year',
    prevDecade: 'Previous Decade',
    prevMonth: 'Previous Month',
    prevYear: 'Previous Year',
    today: 'Today',
    weekHeader: 'Wk',
  },
  pt: {
    aria: {
      close: 'Fechar',
      firstPageLabel: 'Primeira página',
      lastPageLabel: 'Última página',
      next: 'Próximo',
      nextPageLabel: 'Próxima página',
      pageLabel: 'Página {page}',
      prevPageLabel: 'Página anterior',
      previous: 'Anterior',
      rowsPerPageLabel: 'Linhas por página',
    },
    chooseDate: 'Escolha uma data',
    chooseMonth: 'Escolha o mês',
    chooseYear: 'Escolha o ano',
    clear: 'Limpar',
    nextDecade: 'Próxima década',
    nextMonth: 'Próximo mês',
    nextYear: 'Próximo ano',
    prevDecade: 'Década anterior',
    prevMonth: 'Mês anterior',
    prevYear: 'Ano anterior',
    today: 'Hoje',
    weekHeader: 'Sem',
  },
};

// HELPERS
/**
 * Intl renders pt-BR names lowercase ("agosto", "domingo"); PrimeVue's own English set is capitalised.
 * The same function as `toSentenceCaseHelper`, kept local on purpose: `@Helpers` builds the router on
 * import, and this runs while PrimeVue is being installed.
 */
function toSentenceCase(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function buildDayNames(locale: string, weekday: 'long' | 'short'): string[] {
  const FORMATTER = new Intl.DateTimeFormat(locale, { timeZone: 'UTC', weekday });

  return Array.from({ length: 7 }, (unused, index) =>
    toSentenceCase(FORMATTER.format(new Date(REFERENCE_SUNDAY_UTC + index * MILLISECONDS_PER_DAY)))
  );
}

/*
 * The column headings. NOT Intl's `narrow`, which collapses pt-BR to "D S T Q Q S S" — four of the
 * seven collide. `short` minus its trailing period reads unambiguously in both and still fits.
 */
function buildDayNamesMin(locale: string): string[] {
  return buildDayNames(locale, 'short').map((day) => day.replace(/\.$/, ''));
}

function buildMonthNames(locale: string, month: 'long' | 'short'): string[] {
  const FORMATTER = new Intl.DateTimeFormat(locale, { month, timeZone: 'UTC' });

  return Array.from({ length: 12 }, (unused, index) =>
    toSentenceCase(FORMATTER.format(new Date(Date.UTC(2023, index, 1))))
  );
}

function buildPrimeVueLocale(locale: string) {
  const LANGUAGE = locale.split('-')[0] ?? 'en';
  const WORDS = WIDGET_WORDS[LANGUAGE] ?? WIDGET_WORDS.en!;

  return {
    ...WORDS,
    aria: { ...WORDS.aria },
    dayNames: buildDayNames(locale, 'long'),
    dayNamesMin: buildDayNamesMin(locale),
    dayNamesShort: buildDayNames(locale, 'short'),
    monthNames: buildMonthNames(locale, 'long'),
    monthNamesShort: buildMonthNames(locale, 'short'),
  };
}

/**
 * Switches a LIVE PrimeVue catalogue (`usePrimeVue().config.locale`) to another language.
 *
 * At install PrimeVue deep-merges the options over its defaults, so `aria` keeps every key this file
 * does not list. A switch has to do the same by hand: assigning the built `aria` wholesale would
 * erase the rest (`star`, `selectAll`, …) and leave those widgets announcing `undefined`.
 */
function applyPrimeVueLocale(configLocale: { aria?: object }, locale: string): void {
  const { aria, ...rest } = buildPrimeVueLocale(locale);

  Object.assign(configLocale, rest);
  configLocale.aria = { ...configLocale.aria, ...aria };
}

export { applyPrimeVueLocale };
export default buildPrimeVueLocale;
