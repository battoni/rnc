<?php

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;

/*
 * The suite never reaches the network (rnc-3 plan P7, AM18; A27's line is asserted by F9's
 * `NoOutwardClientsTest`). `tests/Pest.php` calls `Http::preventStrayRequests()` before every
 * feature test, so a request nobody faked throws instead of leaving the machine — including one
 * made by code that never names `Http::` in the test file. A test that needs a reply fakes it.
 */
describe('the stray-request guard', function () {
    it('refuses an HTTP call that no test faked', function () {
        expect(fn () => Http::get('https://brasilapi.com.br/api/cnpj/v1/11222333000181'))->toThrow(StrayRequestException::class);
    });

    it('still lets a faked call through', function () {
        Http::fake(['brasilapi.com.br/*' => Http::response(['razao_social' => 'Exemplo'])]);

        expect(Http::get('https://brasilapi.com.br/api/cnpj/v1/11222333000181')->json('razao_social'))->toBe('Exemplo');
    });
});
