<?php

namespace App\Modules\Assistant\Helpers;

use Illuminate\Support\Str;

/**
 * A question as the matcher reads it (rnc-3 R12, the demo's `assistTokens`): lower-case, accents
 * dropped, anything that is not a letter, a digit or a space removed ("NF-e" → "nfe"), split on
 * spaces. Pure — it works on a copy of the text and never builds a pattern from it (plan P6).
 */
final class QuestionNormalizer
{
    private const NOT_A_WORD_CHARACTER = '/[^a-z0-9\s]/';

    private const SPACES = '/\s+/';

    /** @return list<string> */
    public static function tokens(string $text): array
    {
        $plain = (string) preg_replace(self::NOT_A_WORD_CHARACTER, '', mb_strtolower(Str::ascii($text)));

        return array_values(array_filter(
            preg_split(self::SPACES, $plain) ?: [],
            fn (string $word) => $word !== '',
        ));
    }

    /** The words joined by one space: "Quando começa a CBS?" and "quando comeca a cbs" read alike (R15). */
    public static function normalise(string $text): string
    {
        return implode(' ', self::tokens($text));
    }
}
