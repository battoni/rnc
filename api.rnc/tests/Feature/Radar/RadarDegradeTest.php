<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Partner\Models\Partner;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Support\FiscalRows;

/*
 * rnc-4 R19, D16: the Radar needs `portfolio` only. Without `fiscal` it reads every client from the
 * cadastro — no XML evidence, no access key, the declared B2B share — and without `partners` it
 * offers no partner picker and its batch referral is a 404 (ModuleGateTest).
 */
beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->actingAs(FiscalRows::person('gestao'));

    // Declares 20 % B2B; 12 invoices in 90 days say 80 % by value — read as B2B only from the XMLs.
    $this->measured = FiscalRows::client(['name' => 'A Medida Ltda', 'regime' => 'simples', 'sector' => 'servicos', 'band' => 'b2', 'b2b_share' => 20, 'partner_consent' => true]);

    foreach (range(1, 12) as $index) {
        FiscalRows::document($this->measured, 'saida', [
            'total_cents' => $index <= 10 ? 100_000 : 125_000,
            'recipient_kind' => $index <= 10 ? 'cnpj' : 'consumidor',
            'recipient_cnpj' => $index <= 10 ? '99700000000191' : null,
            'issued_at' => CarbonImmutable::now()->subDays(10),
        ]);
    }

    Partner::withoutGlobalScopes()->create([
        'firm_id' => $this->franco->id, 'name' => 'Simples Parceiro (exemplo)', 'specialty' => 'precificacao_controladoria',
        'email' => 'simples@parceiro.exemplo', 'active' => true,
    ]);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function degradeRadarClient(string $rule, int $clientId): ?array
{
    return collect(test()->getJson("/api/radar/types/{$rule}")->assertOk()->json('data.clients'))->firstWhere('id', $clientId);
}

it('proves an item with the XMLs while fiscal is on', function () {
    $client = degradeRadarClient('simples-regular', $this->measured->id);

    expect($client['evidence']['source'])->toBe('notas')
        ->and($client['keys'])->toHaveCount(3);
});

it('reads the client from the cadastro, with no evidence nor key, with fiscal off', function () {
    $this->franco->forceFill(['modules' => ['portfolio', 'radar', 'partners']])->save();

    $client = degradeRadarClient('simples-b2c', $this->measured->id);

    expect(degradeRadarClient('simples-regular', $this->measured->id))->toBeNull()
        ->and($client['evidence'])->toBe(['source' => 'cadastro'])
        ->and($client['keys'])->toBe([]);
});

it('offers no partner on any row with partners off', function () {
    $this->franco->forceFill(['modules' => ['portfolio', 'radar', 'fiscal']])->save();

    $types = $this->getJson('/api/radar')->assertOk()->json('data.types');

    expect($types)->not->toBeEmpty()
        ->and(array_column($types, 'partners'))->each->toBe([])
        ->and($this->getJson('/api/radar/types/simples-regular')->assertOk()->json('data.type.partners'))->toBe([]);
});

it('offers a partner on a row it covers while partners is on', function () {
    $types = $this->getJson('/api/radar')->assertOk()->json('data.types');

    expect(array_merge(...array_column($types, 'partners')))->not->toBeEmpty();
});
