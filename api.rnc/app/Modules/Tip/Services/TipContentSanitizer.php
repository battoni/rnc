<?php

namespace App\Modules\Tip\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * A tip body arrives as HTML from a rich-text editor and is stored as HTML, which is stored XSS
 * unless something stands in the way. This is that thing.
 *
 * SANITISED ON THE WAY IN, NOT ON THE WAY OUT (CRITICAL). Cleaning at render time protects only
 * the screen that remembers to do it; the database would still hold whatever was pasted, and a
 * tip is read by EVERY authenticated user on the screens it is anchored to — a single poisoned
 * row would fire in every operator's browser. What is stored is what has already been made safe.
 *
 * "Only tips.manage writes it" is not the argument. Someone pastes from somewhere, an account is
 * borrowed, the permission is granted more widely later — and what was true when this shipped
 * stops being true without anyone editing this file.
 *
 * The allow-list is deliberately NARROWER than the editor's toolbar can produce rather than
 * wider: anything not listed is dropped, and the worst case is a paragraph losing its styling.
 * `allowSafeElements()` is NOT used — it is generous, and generosity is the wrong instinct here.
 */
class TipContentSanitizer
{
    /**
     * Text-level markup, carrying no attributes and no behaviour.
     *
     * No h1/h2: a tip lives inside a popover a few lines tall, and a heading tier above the
     * surrounding page's would fight the design system rather than serve the reader.
     *
     * @var array<int, string>
     */
    private const PLAIN_ELEMENTS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's',
        'h3', 'h4',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code',
    ];

    public function sanitize(?string $content): string
    {
        if (! $content) {
            return '';
        }

        return trim($this->collapseNonBreakingSpaces($this->sanitizer()->sanitize($content)));
    }

    /**
     * Quill serialises EVERY space as a non-breaking space, so a paragraph typed in the editor
     * comes back as one unbreakable run: it refuses to wrap, overflows its popover, and drags a
     * horizontal scrollbar across whatever screen it lands on.
     *
     * Normalised here rather than patched with CSS at each render site, because the defect is in
     * what is stored — it would also survive into an export, an email, or anything else that
     * reads these rows. A deliberate nbsp is not worth preserving in a two-paragraph tip.
     */
    private function collapseNonBreakingSpaces(string $html): string
    {
        return str_replace(["\u{00A0}", '&nbsp;', '&#160;', '&#xA0;'], ' ', $html);
    }

    /**
     * Whether the content carries any actual text once markup is stripped.
     *
     * The editor emits `<p><br></p>` for an empty document, so "not empty string" is not the
     * same question as "the author wrote something".
     */
    public function hasText(?string $content): bool
    {
        $text = html_entity_decode(strip_tags($content ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\x{00A0}/u', ' ', $text)) !== '';
    }

    private function sanitizer(): HtmlSanitizer
    {
        $config = new HtmlSanitizerConfig;

        foreach (self::PLAIN_ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        /*
         * Links keep only `href`, and only over http(s) — which is what drops `javascript:` and
         * `data:` URLs, the two that turn a link into a script. No `target`: it is added at
         * render time together with `rel="noopener"`, so a stored `target` can never arrive
         * without it.
         */
        $config = $config
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['http', 'https'])
            ->forceHttpsUrls()
            ->allowRelativeLinks(false);

        /*
         * Images are NOT allowed. A tip is a sentence of help inside a popover; an `<img>` would
         * be a remote URL nobody vetted, loading from a third party in every operator's browser
         * on every screen the tip is anchored to, and leaking who looked at what.
         */
        return new HtmlSanitizer($config);
    }
}
