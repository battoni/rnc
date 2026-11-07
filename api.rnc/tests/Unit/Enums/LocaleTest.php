<?php

use App\Enums\Locale;
use Tests\TestCase;

uses(TestCase::class);

/*
 * The chain is the contract: every reader of translatable content resolves through it, so a screen
 * and an e-mail cannot disagree about which language a half-translated row is shown in.
 */

it('asks for the requested language first', function () {
    expect(Locale::fallbackChain('en')[0])->toBe('en')
        ->and(Locale::fallbackChain('pt-BR')[0])->toBe('pt-BR');
});

it('never repeats a language in the chain', function () {
    $chain = Locale::fallbackChain('pt-BR');

    expect($chain)->toEqual(array_values(array_unique($chain)));
});

it('ends at the source language, whatever was asked for', function () {
    config()->set('app.locale', 'pt-BR');

    $chain = Locale::fallbackChain('en');

    expect($chain)->toContain('pt-BR')
        ->and(end($chain))->toBe('pt-BR');
});

it('answers with the source language when nothing is asked for', function () {
    config()->set('app.locale', 'en');

    expect(Locale::default())->toBe(Locale::EN)
        ->and(Locale::fallbackChain()[0])->toBe('en');
});

it('falls back to English when app.locale names a language this app does not speak', function () {
    config()->set('app.locale', 'fr');

    expect(Locale::default())->toBe(Locale::EN);
});

it('resolves a map to the requested language when it is there', function () {
    expect(Locale::resolve(['pt-BR' => 'Praia', 'en' => 'Beach'], 'en'))->toBe('Beach');
});

it('walks the chain when the requested language is missing or empty', function () {
    config()->set('app.locale', 'pt-BR');

    expect(Locale::resolve(['pt-BR' => 'Praia'], 'en'))->toBe('Praia')
        ->and(Locale::resolve(['pt-BR' => 'Praia', 'en' => ''], 'en'))->toBe('Praia');
});

it('answers null when the map has nothing to show', function () {
    expect(Locale::resolve([], 'en'))->toBeNull()
        ->and(Locale::resolve(['en' => null], 'en'))->toBeNull();
});

/*
 * Overnight review (2026-09-17): with the source language declared first, a language the app does
 * not speak got [fr, pt-BR, en] — the source in the middle, English last — so a half-translated row
 * showed English before the language it was written in. And Laravel names the Brazilian locale
 * `pt_BR` (`lang/pt_BR`, `SetLocale`), which this enum did not recognise: `default()` answered
 * English on a Portuguese install.
 */

it('ends at the source language for a language it does not speak', function () {
    config()->set('app.locale', 'pt-BR');

    expect(Locale::fallbackChain('fr'))->toBe(['fr', 'en', 'pt-BR'])
        ->and(Locale::resolve(['pt-BR' => 'Praia', 'en' => 'Beach'], 'fr'))->toBe('Beach');
});

it('ends at the source language when English is the source', function () {
    config()->set('app.locale', 'en');

    expect(Locale::fallbackChain('fr'))->toBe(['fr', 'pt-BR', 'en']);
});

it('reads the Laravel spelling of a locale as the same language', function () {
    config()->set('app.locale', 'pt_BR');

    expect(Locale::default())->toBe(Locale::PT_BR)
        ->and(Locale::fallbackChain('pt_BR'))->toBe(['pt-BR', 'en'])
        ->and(Locale::fallbackChain())->toBe(['pt-BR', 'en']);
});
