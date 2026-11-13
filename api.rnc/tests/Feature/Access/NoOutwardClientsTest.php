<?php

use Symfony\Component\Finder\Finder;

/*
 * A27 (rnc-3 constraints; plan F9, AM8, AM18, AM22): Phase 3 adds no model, no embedding and no video
 * — the assistant answers from reviewed notes only (R13, R16) and the screens carry no player. So no
 * LLM, embedding or video SDK is required in `composer.json` or named in a PHP file under `app/`;
 * the suite cannot reach the network because `tests/Pest.php` forbids a stray request before every
 * feature test (AM18: one line, so a file that reaches HTTP without naming `Http::` is covered
 * too — this test only asserts that line exists); and a local install mails to the log.
 *
 * rnc-2's `tests/Feature/NoOutwardClientsTest.php` (its A24) keeps its own sweep of SEFAZ, LLM and
 * bank SDK namespaces; this file adds Phase 3's words: the video hosts and the vendors' own names.
 */
function phase3OutwardWords(): array
{
    return ['openai', 'anthropic', 'gemini', 'vimeo', 'youtube', 'mux'];
}

it('requires no LLM, embedding or video SDK in composer.json (A27)', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
    $packages = array_keys([...$composer['require'] ?? [], ...$composer['require-dev'] ?? []]);

    $outward = array_values(array_filter(
        $packages,
        fn (string $name) => preg_match('/\b('.implode('|', phase3OutwardWords()).')\b/i', $name) === 1,
    ));

    expect($packages)->not->toBe([])
        ->and($outward)->toBe([]);
});

it('names no LLM, embedding or video client in any PHP file under app/ (A27)', function () {
    $offenders = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        if (preg_match('/\b('.implode('|', phase3OutwardWords()).')\b/i', $file->getContents(), $match) === 1) {
            $offenders[] = $file->getRelativePathname().' ('.$match[1].')';
        }
    }

    expect($offenders)->toBe([]);
});

it('forbids a stray HTTP request before every feature test, in tests/Pest.php (A27, AM18)', function () {
    $pest = (string) file_get_contents(base_path('tests/Pest.php'));

    expect($pest)->toMatch("/^pest\(\)->beforeEach\(fn \(\) => Http::preventStrayRequests\(\)\)->in\('Feature'\);$/m");
});

it('mails to the log on a local install (A27, R27)', function () {
    $example = (string) file_get_contents(base_path('.env.example'));

    expect($example)->toMatch('/^MAIL_MAILER=log$/m')
        ->and(config('mail.default'))->not->toBe('smtp');
});
