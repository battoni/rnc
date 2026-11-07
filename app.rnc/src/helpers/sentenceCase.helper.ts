/*
 * Uppercases the first character and leaves the rest of the string alone.
 *
 * This is what a localized `Intl` label needs, and CSS `capitalize` is not it: pt-BR renders
 * "setembro de 2026", so capitalizing every word promotes the preposition — "Setembro De 2026".
 * The `first-letter:` variant is no substitute either: `::first-letter` applies only to a block
 * container, and most labels sit in an inline span, where the variant silently does nothing.
 *
 * Locales whose Intl output already starts capitalized (en: "September 2026") are unaffected.
 */
export default function toSentenceCase(value: string): string {
  return value.charAt(0).toUpperCase() + value.slice(1);
}
