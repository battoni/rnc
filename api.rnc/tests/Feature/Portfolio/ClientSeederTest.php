<?php

use App\Helpers\Brazil\Cnpj;
use App\Modules\Firm\Models\Firm;
use App\Modules\Portfolio\Models\Client;
use Database\Seeders\DatabaseSeeder;

/*
 * The local portfolio (rnc-1 F8, R25): 500 invented clients of Franco across every sector, regime
 * and band, each with an invented CNPJ that passes the check digits but is plainly not a real one
 * (root `99.9…`, branch `9999`). Never on production.
 */
it('seeds 500 invented clients with valid, plainly invented CNPJs', function () {
    $this->seed();

    $clients = Client::withoutGlobalScopes()->get();

    expect($clients)->toHaveCount(500)
        ->and($clients->pluck('cnpj')->unique())->toHaveCount(500)
        ->and($clients->every(fn (Client $client) => Cnpj::isValid($client->cnpj)))->toBeTrue()
        ->and($clients->every(fn (Client $client) => str_starts_with($client->cnpj, '999') && substr($client->cnpj, 8, 4) === '9999'))->toBeTrue()
        ->and($clients->pluck('sector')->unique())->toHaveCount(6)
        ->and($clients->pluck('regime')->unique()->sort()->values()->all())->toBe(['presumido', 'real', 'simples'])
        ->and($clients->pluck('band')->unique())->toHaveCount(5)
        ->and((float) $clients->min('monthly_fee'))->toBeGreaterThanOrEqual(900.0)
        ->and((float) $clients->max('monthly_fee'))->toBeLessThanOrEqual(9000.0)
        ->and($clients->whereNotNull('repricing'))->toHaveCount(0)
        ->and($clients->pluck('firm_id')->unique()->all())->toBe([Firm::where('slug', 'franco')->value('id')]);
});

it('gives about 85 % of the clients consent through the contract, the rest a no (rnc-2 R28)', function () {
    $this->seed();

    $clients = Client::withoutGlobalScopes()->get();
    $consenting = $clients->where('partner_consent', true)->count();

    expect($consenting)->toBeGreaterThanOrEqual(400)->toBeLessThanOrEqual(450)
        ->and($clients->where('partner_consent', false)->count())->toBe(500 - $consenting)
        ->and($clients->pluck('partner_consent_via')->unique()->all())->toBe(['contrato'])
        ->and($clients->every(fn (Client $client) => $client->partner_consent_at !== null && $client->partner_consent_at->lessThan(now())))->toBeTrue();
});

it('seeds no client on a production database', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DatabaseSeeder::class)->setContainer(app())->__invoke();

    expect(Client::withoutGlobalScopes()->count())->toBe(0);
});
