<?php

use App\Modules\Portfolio\Models\ClientContract;
use App\Modules\Portfolio\Models\ClientEvent;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Tests\Support\FiscalRows;

/*
 * A20's Pest half (rnc-3 R22, AM5): "Cláusula incluída".
 *
 *   POST /api/contracts/{id}/clause   → the contract `{id, has_reforma_clause, clause_marked_at, clause_marked_by}`
 *
 * Marks the contract `has_reforma_clause` with who and when, writes one `contrato.clausula` line on
 * the client's timeline (through `ClientService::recordEvent`) and the row leaves `GET contracts`.
 * Marking again changes nothing and writes no second line. Behind `contracts.manage`; another
 * firm's contract is a 404 (Phase3AccessTest).
 */
beforeEach(function () {
    CarbonImmutable::setTestNow('2026-11-09 13:00:00');
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->reinaldo = FiscalRows::person('ceo');
    $this->client = FiscalRows::client(['name' => 'Acme Indústria Ltda']);
    $this->contract = ClientContract::withoutGlobalScopes()->create([
        'firm_id' => $this->client->firm_id,
        'client_id' => $this->client->id,
        'kind' => 'locacao',
        'annual_value_cents' => 50_000_000,
        'start_year' => 2024,
        'end_year' => 2031,
        'has_reforma_clause' => false,
    ]);
});

it('sets the clause with who and when (R22)', function () {
    $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->contract->id}/clause")
        ->assertOk()
        ->assertJsonPath('data', [
            'id' => $this->contract->id,
            'has_reforma_clause' => true,
            'clause_marked_at' => '2026-11-09T16:00:00+00:00',
            'clause_marked_by' => $this->reinaldo->id,
        ]);

    $stored = $this->contract->fresh();
    expect($stored->has_reforma_clause)->toBeTrue()
        ->and($stored->clause_marked_by)->toBe($this->reinaldo->id)
        ->and($stored->clause_marked_at->toIso8601String())->toBe('2026-11-09T16:00:00+00:00');
});

it('writes one contrato.clausula line on the client timeline, with the user (A20)', function () {
    $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->contract->id}/clause")->assertOk();

    $line = ClientEvent::withoutGlobalScopes()->sole();
    expect($line->only(['firm_id', 'client_id', 'user_id', 'type']))->toBe([
        'firm_id' => $this->client->firm_id,
        'client_id' => $this->client->id,
        'user_id' => $this->reinaldo->id,
        'type' => 'contrato.clausula',
    ])
        ->and($line->data)->toBe(['contract_id' => $this->contract->id, 'kind' => 'locacao', 'end_year' => 2031]);
});

it('drops the row from the alert (A20)', function () {
    expect(array_column($this->actingAs($this->reinaldo)->getJson('/api/contracts')->json('data.contracts'), 'id'))->toBe([$this->contract->id]);

    $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->contract->id}/clause")->assertOk();

    $this->actingAs($this->reinaldo)->getJson('/api/contracts')
        ->assertJsonPath('data.contracts', [])
        ->assertJsonPath('data.summary.at_risk', 0)
        ->assertJsonPath('data.summary.total', 1);
});

it('changes nothing and writes no second line when marked again', function () {
    $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->contract->id}/clause")->assertOk();
    CarbonImmutable::setTestNow('2026-11-10 09:00:00');
    $operator = FiscalRows::person('operador');

    $this->actingAs($operator)->postJson("/api/contracts/{$this->contract->id}/clause")
        ->assertOk()
        ->assertJsonPath('data.clause_marked_at', '2026-11-09T16:00:00+00:00')
        ->assertJsonPath('data.clause_marked_by', $this->reinaldo->id);

    expect(ClientEvent::withoutGlobalScopes()->count())->toBe(1);
});

it('answers 404 on an unknown contract', function () {
    $this->actingAs($this->reinaldo)->postJson('/api/contracts/999999/clause')->assertNotFound()->assertJsonPath('error', 'portfolio.contractNotFound');
});

it('refuses the team with 403', function () {
    $this->actingAs(FiscalRows::person('equipe'))->postJson("/api/contracts/{$this->contract->id}/clause")->assertForbidden();

    expect($this->contract->fresh()->has_reforma_clause)->toBeFalse();
});
