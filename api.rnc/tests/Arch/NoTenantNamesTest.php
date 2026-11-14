<?php

/*
 * A15 (rnc-4 R15, D14): the platform names no tenant. No PHP under `app`, `config`, `resources`,
 * `lang` or `routes` names Franco, Reinaldo, Willy or APW outside a comment, and "RNC" appears only
 * inside the Channel module. Seeders, tests, fixtures and docs (`.md`) are outside the scan by
 * design: a seed may create Franco, a test may assert its name.
 *
 * Comments are read off with PHP's own tokenizer, so a docblock, a `|` banner in a config file and
 * a `{{-- --}}` Blade comment are all left out while a string literal never is.
 */

const TENANT_NAMES = '/\b(?:Franco|Reinaldo|Willy|APW)\b/i';
const NETWORK_ACRONYM = '/\bRNC\b/';
const SCANNED_DIRECTORIES = ['app', 'config', 'resources', 'lang', 'routes'];

/** The source with its comments blanked out, line breaks kept so a hit still names its line. */
function tenantScanWithoutComments(string $source): string
{
    $source = preg_replace_callback('/\{\{--.*?--\}\}/s', fn (array $match) => preg_replace('/[^\n]/', '', $match[0]), $source);

    return collect(token_get_all($source))
        ->map(function (array|string $token) {
            if (is_string($token)) {
                return $token;
            }

            $isComment = in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true);

            return $isComment ? preg_replace('/[^\n]/', '', $token[1]) : $token[1];
        })
        ->implode('');
}

/**
 * Every line of `$sources` (path => PHP source) that matches `$pattern` outside a comment.
 *
 * @param  array<string, string>  $sources
 * @return list<string>
 */
function tenantNamings(array $sources, string $pattern): array
{
    return collect($sources)
        ->flatMap(fn (string $source, string $path) => collect(explode("\n", tenantScanWithoutComments($source)))
            ->filter(fn (string $line) => preg_match($pattern, $line) === 1)
            ->map(fn (string $line, int $index) => "{$path}:".($index + 1).': '.trim($line)))
        ->values()
        ->all();
}

/** @return array<string, string> */
function tenantScannedSources(): array
{
    $root = dirname(__DIR__, 2);

    return collect(SCANNED_DIRECTORIES)
        ->flatMap(fn (string $directory) => iterator_to_array(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator("{$root}/{$directory}", FilesystemIterator::SKIP_DOTS)
        )))
        ->filter(fn (SplFileInfo $file) => $file->getExtension() === 'php')
        ->mapWithKeys(fn (SplFileInfo $file) => [
            substr($file->getPathname(), strlen($root) + 1) => file_get_contents($file->getPathname()),
        ])
        ->all();
}

describe('the platform names no tenant (rnc-4 R15, A15)', function () {
    it('reads every scanned directory', function () {
        $paths = array_keys(tenantScannedSources());

        foreach (SCANNED_DIRECTORIES as $directory) {
            expect(collect($paths)->contains(fn (string $path) => str_starts_with($path, "{$directory}/")))->toBeTrue();
        }
    });

    it('in code, config, views, lang or routes', function () {
        expect(tenantNamings(tenantScannedSources(), TENANT_NAMES))->toBe([]);
    });

    it('says "RNC" only inside the Channel module', function () {
        $outsideChannel = collect(tenantScannedSources())
            ->reject(fn (string $source, string $path) => str_starts_with($path, 'app/Modules/Channel/'))
            ->all();

        expect(tenantNamings($outsideChannel, NETWORK_ACRONYM))->toBe([]);
    });

    it('bites on the shape it guards', function () {
        expect(tenantNamings(['a.php' => "<?php\n\$label = 'Equipe da Franco';"], TENANT_NAMES))->toHaveCount(1)
            ->and(tenantNamings(['a.php' => "<?php\nreturn ['who' => 'reinaldo'];"], TENANT_NAMES))->toHaveCount(1)
            ->and(tenantNamings(['a.blade.php' => '<p>Rede APW</p>'], TENANT_NAMES))->toHaveCount(1)
            ->and(tenantNamings(['a.php' => "<?php\n// Franco's briefing\n/* Reinaldo */\n\$a = 1;"], TENANT_NAMES))->toBe([])
            ->and(tenantNamings(['a.php' => "<?php\n/*\n| one brand, Franco's\n*/\nreturn [];"], TENANT_NAMES))->toBe([])
            ->and(tenantNamings(['a.blade.php' => "{{-- Franco's mark --}}\n<p>ok</p>"], TENANT_NAMES))->toBe([])
            ->and(tenantNamings(['a.php' => "<?php\n\$a = 'Francoise';"], TENANT_NAMES))->toBe([])
            ->and(tenantNamings(['a.php' => "<?php\n\$a = 'Canal RNC';"], NETWORK_ACRONYM))->toHaveCount(1);
    });
});
