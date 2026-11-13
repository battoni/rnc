<?php

use App\Modules\Diagnostic\Contracts\CnpjLookup;
use App\Modules\Diagnostic\Services\BrasilApiCnpjLookup;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/*
 * R9 / A5 (rnc-1 F3, plan AM10): the CNPJ path reads BrasilAPI (`/api/cnpj/v1/{cnpj}`) through the
 * `CnpjLookup` contract. Porte ME → band b1, EPP → b2, anything else → asked; `opcao_pelo_simples`
 * true (or MEI) → Simples, otherwise asked; the sector from the first two digits of the main CNAE.
 * Never the network here: every answer is `Http::fake`d.
 */
const LOOKUP_CNPJ = '11222333000181';

function brasilApiCompany(array $overrides = []): array
{
    return array_merge([
        'cnpj' => LOOKUP_CNPJ,
        'razao_social' => 'MERCADO BOA VISTA LTDA',
        'nome_fantasia' => 'BOA VISTA',
        'cnae_fiscal' => 4711302,
        'cnae_fiscal_descricao' => 'Comércio varejista de mercadorias em geral',
        'codigo_porte' => 1,
        'porte' => 'MICRO EMPRESA',
        'opcao_pelo_simples' => true,
        'opcao_pelo_mei' => false,
    ], $overrides);
}

function fakeBrasilApi(array $company, int $status = 200): void
{
    Http::fake(['brasilapi.com.br/api/cnpj/v1/*' => Http::response($company, $status)]);
}

beforeEach(function () {
    Cache::flush();
    // Never the network: a lookup nothing faked fails the test instead of asking BrasilAPI.
    Http::preventStrayRequests();
});

it('is what the container hands out for the contract', function () {
    expect(app(CnpjLookup::class))->toBeInstanceOf(BrasilApiCnpjLookup::class);
});

it('maps a micro company on the Simples to its name, sector, band and regime', function () {
    fakeBrasilApi(brasilApiCompany());

    $company = app(CnpjLookup::class)->lookup(LOOKUP_CNPJ);

    expect($company->name)->toBe('MERCADO BOA VISTA LTDA')
        ->and($company->cnae)->toBe('4711302')
        ->and($company->cnae_description)->toBe('Comércio varejista de mercadorias em geral')
        ->and($company->sector)->toBe('comercio')
        ->and($company->band)->toBe('b1')
        ->and($company->regime)->toBe('simples');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://brasilapi.com.br/api/cnpj/v1/'.LOOKUP_CNPJ);
});

it('maps a small company (EPP) outside the Simples to band b2 and an unknown regime', function () {
    fakeBrasilApi(brasilApiCompany(['codigo_porte' => 3, 'porte' => 'EMPRESA DE PEQUENO PORTE', 'opcao_pelo_simples' => false]));

    $company = app(CnpjLookup::class)->lookup(LOOKUP_CNPJ);

    expect($company->band)->toBe('b2')->and($company->regime)->toBeNull();
});

it('asks the band of any other porte and the regime when the Simples option is unknown', function () {
    fakeBrasilApi(brasilApiCompany(['codigo_porte' => 5, 'porte' => 'DEMAIS', 'opcao_pelo_simples' => null]));

    $company = app(CnpjLookup::class)->lookup(LOOKUP_CNPJ);

    expect($company->band)->toBeNull()->and($company->regime)->toBeNull();
});

it('reads a MEI as the Simples', function () {
    fakeBrasilApi(brasilApiCompany(['opcao_pelo_simples' => false, 'opcao_pelo_mei' => true]));

    expect(app(CnpjLookup::class)->lookup(LOOKUP_CNPJ)->regime)->toBe('simples');
});

it('takes the sector from the first two digits of the main CNAE', function (int $cnae, string $sector) {
    fakeBrasilApi(brasilApiCompany(['cnae_fiscal' => $cnae]));

    expect(app(CnpjLookup::class)->lookup(LOOKUP_CNPJ)->sector)->toBe($sector);
})->with([
    'agro 01' => [111301, 'agro'],
    'agro 03' => [322101, 'agro'],
    'indústria 05' => [500301, 'industria'],
    'indústria 33' => [3314710, 'industria'],
    'comércio 45' => [4520001, 'comercio'],
    'comércio 47' => [4711302, 'comercio'],
    'educação 85' => [8513900, 'educacao'],
    'saúde 86' => [8630501, 'saude'],
    'saúde 88' => [8800600, 'saude'],
    'serviços 62' => [6201501, 'servicos'],
    'serviços 35' => [3511501, 'servicos'],
]);

it('answers null for an unknown CNPJ, a server error or a timeout', function (Closure $fake) {
    $fake();

    expect(app(CnpjLookup::class)->lookup(LOOKUP_CNPJ))->toBeNull();
})->with([
    'not found' => fn () => fakeBrasilApi(['message' => 'CNPJ não encontrado'], 404),
    'server error' => fn () => fakeBrasilApi(['message' => 'erro'], 500),
    'timeout' => fn () => Http::fake(fn () => throw new ConnectionException('Operation timed out after 5000 milliseconds')),
]);

it('caches a found company for 30 days under cnpj:{14 digits}', function () {
    fakeBrasilApi(brasilApiCompany());

    app(CnpjLookup::class)->lookup(LOOKUP_CNPJ);
    app(CnpjLookup::class)->lookup(LOOKUP_CNPJ);

    Http::assertSentCount(1);
    expect(Cache::has('cnpj:'.LOOKUP_CNPJ))->toBeTrue();

    $this->travel(29)->days();
    expect(Cache::has('cnpj:'.LOOKUP_CNPJ))->toBeTrue();

    $this->travel(2)->days();
    expect(Cache::has('cnpj:'.LOOKUP_CNPJ))->toBeFalse();
});

it('does not cache a failure, so the next visitor asks again', function () {
    fakeBrasilApi(['message' => 'erro'], 500);

    app(CnpjLookup::class)->lookup(LOOKUP_CNPJ);

    expect(Cache::has('cnpj:'.LOOKUP_CNPJ))->toBeFalse();
});
