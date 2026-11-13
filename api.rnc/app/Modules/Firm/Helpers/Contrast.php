<?php

namespace App\Modules\Firm\Helpers;

/**
 * WCAG 2 contrast between two `#rrggbb` colours (rnc-3 D4): relative luminance per channel, then
 * (lighter + 0,05) / (darker + 0,05), from 1 (none) to 21 (black on white). Pure.
 *
 * A brand's primary carries white text on the site's buttons and header, so it must reach
 * `AA_MINIMUM` against white or the re-theme breaks the site's legibility.
 */
final class Contrast
{
    public const AA_MINIMUM = 4.5;

    private const WHITE = '#ffffff';

    /** The sRGB channel below which the curve is linear (WCAG 2.x). */
    private const LINEAR_THRESHOLD = 0.03928;

    public static function ratio(string $first, string $second): float
    {
        $lighter = max(self::luminance($first), self::luminance($second));
        $darker = min(self::luminance($first), self::luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function whiteOn(string $background): float
    {
        return self::ratio(self::WHITE, $background);
    }

    /** @param  string  $hex  `#rrggbb`, either case */
    private static function luminance(string $hex): float
    {
        [$red, $green, $blue] = array_map(
            fn (string $channel) => self::linear(hexdec($channel) / 255),
            str_split(substr($hex, 1, 6), 2),
        );

        return 0.2126 * $red + 0.7152 * $green + 0.0722 * $blue;
    }

    private static function linear(float $channel): float
    {
        return $channel <= self::LINEAR_THRESHOLD ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4;
    }
}
