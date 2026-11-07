/*
 * Tip bodies are stored as sanitised HTML with `href` as the only surviving attribute on a link —
 * `target` and `rel` are deliberately NOT stored (`TipContentSanitizer`), so a stored `target` can
 * never arrive without the `rel` that makes it safe. They are added here instead, at render time,
 * always as a pair.
 *
 * A tip is help attached to a screen someone is working on. A link that navigates in the same tab
 * takes them off that screen — and off whatever they had half-filled on it. (from new.impressao)
 */
const ANCHOR_OPEN_TAG = /<a\b([^>]*)>/gi;
const STORED_TARGET_OR_REL = /\s+(?:target|rel)\s*=\s*("[^"]*"|'[^']*'|[^\s>]*)/gi;

/** Makes every link in sanitised rich text open in a new tab, safely. Idempotent. */
function withExternalLinks(html: string): string {
  if (!html) return '';

  return html.replace(
    ANCHOR_OPEN_TAG,
    (_match, attributes: string) =>
      `<a${attributes.replace(STORED_TARGET_OR_REL, '')} target="_blank" rel="noopener noreferrer">`
  );
}

export { withExternalLinks };
