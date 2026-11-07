<?php

namespace App\Modules\Idea\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The Novidades body arrives as HTML from a rich-text editor, and is stored as HTML. That is
 * stored XSS unless something stands in the way, and this is that thing.
 *
 * SANITISED ON THE WAY IN, NOT ON THE WAY OUT (CRITICAL). Cleaning at render time protects only
 * the screen that remembers to do it; the database would still hold whatever was pasted, and every
 * other consumer of `GET /api/news` — a future app, an export, an email — would receive it raw.
 * What is stored is what has already been made safe.
 *
 * "Only admins write it" is not the argument. An admin pastes from somewhere, an account is
 * borrowed, a permission is granted more widely later — and the thing that was true when the
 * feature shipped stops being true without anyone editing this file.
 *
 * The allow-list is deliberately narrower than the editor's toolbar can produce rather than wider:
 * anything the editor emits that is not listed is dropped, and the worst case is a paragraph
 * losing its styling. `allowSafeElements()` is NOT used — it is generous, and generosity is the
 * wrong instinct here.
 */
class NewsBodySanitizer
{
    /** Text-level markup, carrying no attributes and no behaviour. */
    private const PLAIN_ELEMENTS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'hr',
    ];

    public function sanitize(?string $body): string
    {
        if (! $body) {
            return '';
        }

        return $this->sanitizer()->sanitize($body);
    }

    private function sanitizer(): HtmlSanitizer
    {
        $config = new HtmlSanitizerConfig;

        foreach (self::PLAIN_ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        /*
         * Links keep only `href`, and only over http(s) — which is what drops `javascript:` and
         * `data:` URLs, the two that turn a link into a script. No `target`: it is added at render
         * time together with `rel="noopener"`, so a stored `target` can never arrive without it.
         */
        $config = $config
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['http', 'https'])
            ->forceHttpsUrls()
            ->allowRelativeLinks(false);

        /*
         * Images are NOT allowed in the body, on purpose. An entry has a cover, which is uploaded
         * through this API and served from a disk we control; an `<img>` in the body would be a
         * remote URL nobody vetted, loading from a third party on every reader's browser and
         * leaking who read what. If the body needs pictures, they should be uploaded like the
         * cover is.
         */
        return new HtmlSanitizer($config);
    }
}
