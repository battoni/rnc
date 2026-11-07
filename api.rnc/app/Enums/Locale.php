<?php

namespace App\Enums;

/**
 * The languages this application speaks, and the order it tries them in.
 *
 * Translatable content is stored as a map keyed by these values (see `App\Support\HasTranslations`),
 * and a map is never guaranteed to be complete: an admin writes the source language first and the
 * others arrive later, if ever. So reading one always needs an ORDER, and the order has to be the
 * same everywhere — a screen that falls back differently from an e-mail shows two answers for one
 * row. `fallbackChain()` is that single order, and it always ends at the source language.
 *
 * Adding a language is one case here. The chain stays deterministic because it is built from the
 * declaration order, never from what happens to be in the map.
 */
enum Locale: string
{
    case PT_BR = 'pt-BR';
    case EN = 'en';

    /**
     * The source language: what an entity is written in before anyone translates it.
     *
     * Read from `app.locale` rather than hard-coded, because a project changes that in `.env` and
     * would otherwise have a fallback chain ending somewhere its content never lives.
     */
    public static function default(): self
    {
        return self::tryFrom(self::normalize((string) config('app.locale'))) ?? self::EN;
    }

    /**
     * Laravel spells the Brazilian locale `pt_BR` (`lang/pt_BR`, `SetLocale`); a translations map
     * and this enum spell it `pt-BR`. Both name one language, so a code is read with a hyphen and
     * matched to a case regardless of letter case. A code this app does not speak is returned as
     * it came, hyphenated.
     */
    public static function normalize(string $locale): string
    {
        $hyphenated = str_replace('_', '-', trim($locale));

        foreach (self::all() as $known) {
            if (strcasecmp($known, $hyphenated) === 0) {
                return $known;
            }
        }

        return $hyphenated;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return array_map(fn (self $locale): string => $locale->value, self::cases());
    }

    /**
     * Requested first, then every other language in declaration order, and the source LAST.
     * Deduplicated, so no language appears twice.
     *
     * The source is taken out of the middle before it is appended: deduplicating a plain
     * `[requested, ...all(), source]` kept the source wherever it was declared, so with `pt-BR`
     * first a request for `fr` ended at English.
     *
     * @return list<string>
     */
    public static function fallbackChain(?string $locale = null): array
    {
        $source = self::default()->value;
        $others = array_filter(self::all(), fn (string $candidate): bool => $candidate !== $source);
        $requested = $locale === null ? $source : self::normalize($locale);
        $chain = [];

        foreach ([$requested, ...$others, $source] as $candidate) {
            if ($candidate !== '' && ! in_array($candidate, $chain, true)) {
                $chain[] = $candidate;
            }
        }

        return $chain;
    }

    /**
     * Collapse a translations map to the one string a reader gets.
     *
     * An empty string counts as missing: a field cleared in the admin is not a translation.
     *
     * @param  array<string, string|null>  $translations
     */
    public static function resolve(array $translations, ?string $locale = null): ?string
    {
        foreach (self::fallbackChain($locale) as $key) {
            if (! empty($translations[$key])) {
                return $translations[$key];
            }
        }

        return null;
    }
}
