<?php

use Symfony\Component\Finder\Finder;

/*
 * Nothing calls out (rnc-2 A24; D5, D9, D11): no SEFAZ, LLM or bank client exists in the code, and
 * no test can reach the network — every Pest file that fakes HTTP also forbids a stray request, so a
 * call nobody faked fails instead of leaving the machine.
 *
 * The namespaces are the PHP SDKs a later phase would reach for: NF-e/SEFAZ (sped-nfe, nfephp),
 * LLMs (OpenAI, Anthropic, Gemini, Mistral, Ollama, LangChain-style wrappers, Prism) and banks or
 * payment rails (Pix, Stripe, Pagar.me, Mercado Pago, PagSeguro, Asaas, Efí/Gerencianet, Iugu).
 */
const OUTWARD_CLIENT_NAMESPACES = [
    'NFePHP',
    'Sped',
    'OpenAI',
    'Anthropic',
    'Gemini',
    'Mistral',
    'Ollama',
    'LLPhant',
    'Prism',
    'EchoLabs',
    'Stripe',
    'PagarMe',
    'Pagarme',
    'MercadoPago',
    'PagSeguro',
    'Asaas',
    'Gerencianet',
    'Efi',
    'Iugu',
    'Piggly',
];

/** Composer vendors of those SDKs: none may be installed (an SDK not installed is one `arch` cannot see). */
const OUTWARD_CLIENT_PACKAGE_PREFIXES = [
    'nfephp-org/',
    'openai-php/',
    'anthropic-ai/',
    'google-gemini-php/',
    'mozex/anthropic',
    'echolabsdev/prism',
    'prism-php/',
    'theodo-group/llphant',
    'stripe/',
    'pagarme/',
    'mercadopago/',
    'pagseguro/',
    'efipay/',
    'gerencianet/',
    'iugu/',
    'piggly/',
];

arch('no class under app/ uses a SEFAZ, LLM or bank SDK (A24)')
    ->expect('App')
    ->not->toUse(OUTWARD_CLIENT_NAMESPACES);

arch('no seeder or database support class uses one either (A24)')
    ->expect('Database')
    ->not->toUse(OUTWARD_CLIENT_NAMESPACES);

it('installs no SEFAZ, LLM or bank SDK package (A24)', function () {
    $lock = json_decode((string) file_get_contents(base_path('composer.lock')), true);
    $installed = array_column([...$lock['packages'], ...$lock['packages-dev']], 'name');

    $outward = array_values(array_filter(
        $installed,
        fn (string $name) => array_filter(OUTWARD_CLIENT_PACKAGE_PREFIXES, fn (string $prefix) => str_starts_with($name, $prefix)) !== [],
    ));

    expect($installed)->not->toBe([])
        ->and($outward)->toBe([]);
});

it('has every test file that fakes HTTP also forbid stray requests (A24)', function () {
    $offenders = [];

    foreach (Finder::create()->files()->in(base_path('tests'))->name('*.php') as $file) {
        $source = $file->getContents();
        $fakesHttp = str_contains($source, 'Http::fake');
        $forbidsStray = str_contains($source, 'Http::preventStrayRequests()');

        if ($fakesHttp && ! $forbidsStray) {
            $offenders[] = $file->getRelativePathname();
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps the mail of a local install on the log (A24)', function () {
    $example = (string) file_get_contents(base_path('.env.example'));

    expect($example)->toMatch('/^MAIL_MAILER=log$/m')
        ->and(config('mail.default'))->not->toBe('smtp');
});
