<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Portfolio\Models\ClientContract;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Support\FiscalRows;

/*
 * A20 (rnc-4 R19): the contract alert without the network. With `contracts` on and `partners` off,
 * "Encaminhar ao jurídico" is gone — its picker and its referral answer 404 — and the alert itself
 * answers no referral field: no client consent, no "Com <parceiro>", no "Encaminhados" count.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->manager = FiscalRows::person('gestao');
    $client = FiscalRows::client(['name' => 'Acme Indústria Ltda', 'partner_consent' => true]);
    $this->contract = ClientContract::withoutGlobalScopes()->create([
        'firm_id' => $client->firm_id, 'client_id' => $client->id, 'kind' => 'fornecimento',
        'annual_value_cents' => 1_000_000, 'start_year' => 2025, 'end_year' => 2030, 'has_reforma_clause' => false,
    ]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('answers the referral fields while partners is on', function () {
    $this->franco->forceFill(['modules' => ['portfolio', 'contracts', 'partners']])->save();

    $alert = $this->actingAs($this->manager)->getJson('/api/contracts')->assertOk()->json('data');

    expect($alert['summary'])->toHaveKey('referred')
        ->and($alert['contracts'][0])->toHaveKey('referred_to')
        ->and($alert['contracts'][0]['client'])->toHaveKey('partner_consent');
});

it('answers 404 on the legal picker and the referral with partners off (A20)', function () {
    $this->franco->forceFill(['modules' => ['portfolio', 'contracts']])->save();
    $this->actingAs($this->manager);

    $this->getJson('/api/contracts/legal-partners')->assertNotFound();
    $this->postJson("/api/contracts/{$this->contract->id}/referrals", ['partner_id' => 1])->assertNotFound();
});

it('answers the alert without a referral field with partners off (A20)', function () {
    $this->franco->forceFill(['modules' => ['portfolio', 'contracts']])->save();

    $alert = $this->actingAs($this->manager)->getJson('/api/contracts')->assertOk()->json('data');

    expect($alert['summary'])->not->toHaveKey('referred')
        ->and($alert['summary']['at_risk'])->toBe(1)
        ->and($alert['contracts'][0])->not->toHaveKey('referred_to')
        ->and($alert['contracts'][0]['client'])->not->toHaveKeys(['partner_consent', 'partner_consent_via'])
        ->and($alert['contracts'][0]['client']['name'])->toBe('Acme Indústria Ltda');
});
