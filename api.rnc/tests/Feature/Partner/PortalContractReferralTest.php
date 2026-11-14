<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Models\ClientContract;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FiscalRows;

/*
 * A19 (rnc-3 R21, AM20): a contract referral in the partner's portal.
 *
 *   GET /api/portal/referrals[/{id}]   … contract: {kind, end_year, sem_clausula, value_range: {min_cents, max_cents, label}}
 *
 * The partner sees the contract's kind, its end year, that it has no Reforma clause and its annual
 * value as a RANGE of fixed bands — never the exact `annual_value_cents` (the client's prices are
 * Franco's), never the client's fee, its other contracts or its other referrals — and Franco's
 * contact as the way in. Another partner gets 404. A plain referral answers `contract: null`.
 */
function portalContractPartner(string $name): Partner
{
    return Partner::withoutGlobalScopes()->create([
        'firm_id' => FiscalRows::francoId(),
        'name' => $name,
        'specialty' => 'advocacia_tributaria',
        'email' => str($name)->slug().'@parceiro.exemplo',
        'active' => true,
    ]);
}

function portalContractLogin(Partner $partner): User
{
    return User::factory()->create([
        'firm_id' => $partner->firm_id,
        'role_id' => Role::where('slug', 'parceiro')->value('id'),
        'partner_id' => $partner->id,
    ]);
}

function portalContractOf(Client $client, array $attributes = []): ClientContract
{
    return ClientContract::withoutGlobalScopes()->create([
        'firm_id' => $client->firm_id,
        'client_id' => $client->id,
        'kind' => 'prestacao_servicos',
        'annual_value_cents' => 73_456_789,
        'start_year' => 2023,
        'end_year' => 2032,
        'has_reforma_clause' => false,
        ...$attributes,
    ]);
}

beforeEach(function () {
    Mail::fake();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->reinaldo = FiscalRows::person('gestao');
    $this->andrade = portalContractPartner('Andrade Tributário (exemplo)');
    $this->bastos = portalContractPartner('Bastos Advocacia (exemplo)');
    $this->andradeLogin = portalContractLogin($this->andrade);
    $this->bastosLogin = portalContractLogin($this->bastos);

    $this->client = FiscalRows::client([
        'name' => 'Acme Indústria Ltda', 'monthly_fee' => 4321.98, 'partner_consent' => true,
        'partner_consent_at' => CarbonImmutable::parse('2019-01-15T12:00:00Z'), 'partner_consent_via' => 'contrato',
    ]);
    $this->contract = portalContractOf($this->client);
    $this->other = portalContractOf($this->client, ['kind' => 'locacao', 'annual_value_cents' => 12_345_678, 'end_year' => 2029]);

    $this->referralId = $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->contract->id}/referrals", ['partner_id' => $this->andrade->id])
        ->assertCreated()->json('data.id');
    $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->other->id}/referrals", ['partner_id' => $this->bastos->id])->assertCreated();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('shows the partner the contract summary with a value range, not the exact value (A19, AM20)', function () {
    $referral = $this->actingAs($this->andradeLogin)->getJson("/api/portal/referrals/{$this->referralId}")->assertOk()->json('data');

    expect($referral['company'])->toBe('Acme Indústria Ltda')
        ->and($referral['contract'])->toBe([
            'kind' => 'prestacao_servicos',
            'end_year' => 2032,
            'sem_clausula' => true,
            'value_range' => ['min_cents' => 50_000_000, 'max_cents' => 100_000_000, 'label' => 'R$ 500 mil a R$ 1 mi'],
        ]);
});

it('never sends the exact value, the fee, the other contract or the other referral anywhere in the payload (A19)', function () {
    $inbox = $this->actingAs($this->andradeLogin)->getJson('/api/portal/referrals')->assertOk();
    $payload = $inbox->getContent().$this->actingAs($this->andradeLogin)->getJson("/api/portal/referrals/{$this->referralId}")->getContent();

    expect($inbox->json('data'))->toHaveCount(1)
        ->and($payload)->not->toContain('annual_value_cents')
        ->and($payload)->not->toContain('73456789')
        ->and($payload)->not->toContain('734.567')
        ->and($payload)->not->toContain('monthly_fee')
        ->and($payload)->not->toContain('4321')
        ->and($payload)->not->toContain('locacao')
        ->and($payload)->not->toContain('2029')
        ->and($payload)->not->toContain('Bastos');
});

it("gives Franco's contact as the way in once accepted, never the client's", function () {
    $this->actingAs($this->andradeLogin)->postJson("/api/portal/referrals/{$this->referralId}/transition", ['to' => 'aceito'])->assertOk();

    expect($this->actingAs($this->andradeLogin)->getJson("/api/portal/referrals/{$this->referralId}")->json('data.contact'))
        ->toMatchArray(['via' => 'firm', 'name' => 'Franco Contabilidade']);
});

it('stops showing "sem cláusula" once Franco marks the clause', function () {
    $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->contract->id}/clause")->assertOk();

    expect($this->actingAs($this->andradeLogin)->getJson("/api/portal/referrals/{$this->referralId}")->json('data.contract.sem_clausula'))->toBeFalse();
});

it('answers 404 to another partner asking for this referral (A19)', function () {
    $this->actingAs($this->bastosLogin)->getJson("/api/portal/referrals/{$this->referralId}")->assertNotFound();
});

it('answers contract null on a plain referral', function () {
    $leadId = (int) Referral::withoutGlobalScopes()->findOrFail($this->referralId)->lead_id;
    $plain = $this->actingAs($this->reinaldo)->postJson("/api/leads/{$leadId}/referrals", ['partner_id' => $this->andrade->id, 'message' => 'Podem olhar?'])
        ->assertCreated()->json('data.referral.id');

    expect($this->actingAs($this->andradeLogin)->getJson("/api/portal/referrals/{$plain}")->assertOk()->json('data.contract'))->toBeNull();
});
