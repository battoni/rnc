<?php

namespace App\Support;

use App\Enums\Locale;

/**
 * Translatable fields stored as a JSON map (`{"pt-BR": …, "en": …}`), cast to `array` on the model.
 *
 * A column per language does not survive a third language; a table of translations makes every read
 * a join for content that is always read whole. The map is the middle: one column, one row, and the
 * admin's source of truth stays legible in the database.
 *
 * `translate()` is the only reader — it collapses the map through `Locale::fallbackChain()`, so a
 * missing translation degrades to the source language instead of rendering an empty screen.
 *
 * No bootstrap model uses it yet — a project opts in — so PHPStan would report it as unused and
 * skip it; the ignore on the declaration says that on purpose. Its unit test covers the code.
 */
trait HasTranslations // @phpstan-ignore trait.unused
{
    public function translate(string $field, ?string $locale = null): ?string
    {
        $value = $this->getAttribute($field);

        // A field that is not a map is content written before the column became translatable, and
        // a plain string is still an answer — better than null on a screen that expects prose.
        if (! is_array($value)) {
            return is_string($value) ? $value : null;
        }

        return Locale::resolve($value, $locale);
    }
}
