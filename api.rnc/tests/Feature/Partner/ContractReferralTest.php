<?php

use App\Models\User;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Helpers\ConsentTerms;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Partner\Mail\ReferralSent;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Portfolio\Models\Client;
use App\Modules\Portfolio\Models\ClientContract;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Support\FiscalRows;

/*
 * A18 (rnc-3 R20, D11, AM11): "Encaminhar ao jurídico", behind `contracts.manage`.
 *
 *   GET  /api/contracts/legal-partners       [{id, name, specialty}] advocacia first, then precificação
 *   POST /api/contracts/{id}/referrals       {partner_id} → the referral (201)
 *
 * The partner must be an active partner of the firm with specialty `advocacia_tributaria` or
 * `precificacao_controladoria` (else 422 `partner_not_legal`); the client must have said yes in its
 * contract (`partner_consent` true, else 422 `consent_required`). The referral sits on the client's
 * one portfolio lead (`findOrStartPortfolioLead`) with `contract_id`, `subject_key = contrato:<id>`
 * and the demo's message (the value as a range, AM20); one per contract and partner (409
 * `already_referred`), and a Radar or plain referral of the same lead to the same partner is not
 * blocked by it, nor it by them.
 */
/** The generic contract sentence (rnc-4 D12), Franco's name filled. */
const CONTRACT_CONSENT = 'O contrato de serviços autoriza Franco Contabilidade a apresentar o cliente a especialistas parceiros quando o Radar da Reforma indicar.';

function contractRefPartner(string $name, string $specialty, array $attributes = [], ?int $firmId = null): Partner
{
    return Partner::withoutGlobalScopes()->create([
        'firm_id' => $firmId ?? FiscalRows::francoId(),
        'name' => $name,
        'specialty' => $specialty,
        'email' => str($name)->slug().'@parceiro.exemplo',
        'active' => true,
        ...$attributes,
    ]);
}

/** A client the Radar's `contratos` rule finds, consented `via contrato` in 2019 unless told otherwise. */
function contractRefClient(string $name, ?bool $consent = true, ?int $firmId = null): Client
{
    return FiscalRows::client([
        'name' => $name,
        'sector' => 'servicos',
        'regime' => 'presumido',
        'band' => 'b3',
        'b2b_share' => 60,
        'partner_consent' => $consent,
        'partner_consent_at' => $consent === null ? null : CarbonImmutable::parse('2019-01-15T12:00:00Z'),
        'partner_consent_via' => $consent === null ? null : 'contrato',
    ], $firmId);
}

function contractRefContract(Client $client, array $attributes = []): ClientContract
{
    return ClientContract::withoutGlobalScopes()->create([
        'firm_id' => $client->firm_id,
        'client_id' => $client->id,
        'kind' => 'fornecimento',
        'annual_value_cents' => 64_000_000,
        'start_year' => 2024,
        'end_year' => 2031,
        'has_reforma_clause' => false,
        ...$attributes,
    ]);
}

function contractRefSend(ClientContract $contract, int $partnerId): TestResponse
{
    return test()->postJson("/api/contracts/{$contract->id}/referrals", ['partner_id' => $partnerId]);
}

beforeEach(function () {
    Mail::fake();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->reinaldo = FiscalRows::person('ceo');

    $this->andrade = contractRefPartner('Andrade Tributário (exemplo)', 'advocacia_tributaria');
    $this->bastos = contractRefPartner('Bastos Advocacia (exemplo)', 'advocacia_tributaria');
    $this->margem = contractRefPartner('Aferição Preços (exemplo)', 'precificacao_controladoria');
    $this->seguros = contractRefPartner('Amparo Seguros (exemplo)', 'seguros_beneficios');
    $this->retired = contractRefPartner('Antigo Jurídico (exemplo)', 'advocacia_tributaria', ['active' => false]);

    $this->client = contractRefClient('Acme Indústria Ltda');
    $this->contract = contractRefContract($this->client);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('the legal picker (A18)', function () {
    it('lists the active advocacia partners before the precificação ones (even one named first), each by name', function () {
        $this->actingAs($this->reinaldo)->getJson('/api/contracts/legal-partners')->assertOk()->assertJsonPath('data', [
            ['id' => $this->andrade->id, 'name' => 'Andrade Tributário (exemplo)', 'specialty' => 'advocacia_tributaria'],
            ['id' => $this->bastos->id, 'name' => 'Bastos Advocacia (exemplo)', 'specialty' => 'advocacia_tributaria'],
            ['id' => $this->margem->id, 'name' => 'Aferição Preços (exemplo)', 'specialty' => 'precificacao_controladoria'],
        ]);
    });

    it('is empty without a legal partner in the network, and lists none of firm B\'s', function () {
        Partner::withoutGlobalScopes()->whereIn('specialty', ['advocacia_tributaria', 'precificacao_controladoria'])->update(['active' => false]);
        contractRefPartner('Jurídico Alheio', 'advocacia_tributaria', [], Firm::factory()->create()->id);

        $this->actingAs($this->reinaldo)->getJson('/api/contracts/legal-partners')->assertOk()->assertJsonPath('data', []);
    });
});

describe('the referral (A18)', function () {
    it('sits on the client\'s portfolio lead with contract_id, the subject key and the demo message', function () {
        $answer = $this->actingAs($this->reinaldo)->postJson("/api/contracts/{$this->contract->id}/referrals", ['partner_id' => $this->andrade->id])
            ->assertCreated()
            ->json('data');

        $referral = Referral::withoutGlobalScopes()->with('lead.latestConsent')->findOrFail($answer['id']);
        $lead = $referral->lead;

        expect($lead->client_id)->toBe($this->client->id)
            ->and($lead->channel)->toBe('carteira')
            ->and($lead->company)->toBe('Acme Indústria Ltda')
            ->and($referral->contract_id)->toBe($this->contract->id)
            ->and($referral->subject_key)->toBe('contrato:'.$this->contract->id)
            ->and($referral->partner_id)->toBe($this->andrade->id)
            ->and($referral->status)->toBe('enviado')
            ->and($referral->consent_text)->toBe(CONTRACT_CONSENT)
            ->and($lead->latestConsent->via)->toBe(ConsentTerms::VIA_CONTRACT)
            ->and($referral->message)->toBe('A Franco Contabilidade indica a Acme Indústria Ltda: o contrato de fornecimento de R$ 500 mil a R$ 1 mi por ano vai até 2031 sem cláusula de reequilíbrio para a Reforma. Podem revisar e propor o aditivo? A Franco Contabilidade segue responsável pelo cliente.')
            ->and($referral->message)->not->toContain('640')
            ->and($answer['partner']['id'])->toBe($this->andrade->id)
            ->and(LeadEvent::where('lead_id', $lead->id)->where('type', 'encaminhamento.enviado')->count())->toBe(1);
        Mail::assertQueued(ReferralSent::class, fn (ReferralSent $mail) => $mail->hasTo($this->andrade->email));
    });

    it('goes to a precificação partner too', function () {
        $this->actingAs($this->reinaldo);

        contractRefSend($this->contract, $this->margem->id)->assertCreated();
    });

    it('refuses a partner of another specialty with 422 partner_not_legal', function () {
        $this->actingAs($this->reinaldo);

        contractRefSend($this->contract, $this->seguros->id)->assertUnprocessable()->assertJsonPath('code', 'partner_not_legal');

        expect(Referral::withoutGlobalScopes()->count())->toBe(0);
    });

    it('refuses an inactive partner or another firm\'s on partner_id', function () {
        $foreign = contractRefPartner('Jurídico Alheio', 'advocacia_tributaria', [], Firm::factory()->create()->id);
        $this->actingAs($this->reinaldo);

        contractRefSend($this->contract, $this->retired->id)->assertUnprocessable()->assertJsonValidationErrors('partner_id');
        contractRefSend($this->contract, $foreign->id)->assertUnprocessable()->assertJsonValidationErrors('partner_id');
    });

    it('refuses a client whose contract said no, or never answered, with 422 consent_required and writes nothing', function (?bool $consent) {
        $contract = contractRefContract(contractRefClient('Sem Autorização Ltda', $consent));
        $this->actingAs($this->reinaldo);

        contractRefSend($contract, $this->andrade->id)->assertUnprocessable()->assertJsonPath('code', 'consent_required');

        expect(Referral::withoutGlobalScopes()->count())->toBe(0)
            ->and(Lead::withoutGlobalScopes()->where('client_id', $contract->client_id)->exists())->toBeFalse();
    })->with(['said no' => false, 'never answered' => null]);

    it('answers 409 already_referred to a second send to the same partner, and takes another partner', function () {
        $this->actingAs($this->reinaldo);
        contractRefSend($this->contract, $this->andrade->id)->assertCreated();

        contractRefSend($this->contract, $this->andrade->id)->assertConflict()->assertJsonPath('code', 'already_referred');
        contractRefSend($this->contract, $this->bastos->id)->assertCreated();

        expect(Referral::withoutGlobalScopes()->where('contract_id', $this->contract->id)->count())->toBe(2)
            ->and(Lead::withoutGlobalScopes()->where('client_id', $this->client->id)->count())->toBe(1);
    });

    it('sends two contracts of one client to the same partner, on the one lead', function () {
        $second = contractRefContract($this->client, ['kind' => 'locacao']);
        $this->actingAs($this->reinaldo);

        contractRefSend($this->contract, $this->andrade->id)->assertCreated();
        contractRefSend($second, $this->andrade->id)->assertCreated();

        expect(Referral::withoutGlobalScopes()->where('partner_id', $this->andrade->id)->distinct()->count('lead_id'))->toBe(1);
    });

    it("answers 404 on firm B's contract", function () {
        $theirs = contractRefContract(contractRefClient('Cliente da Firma B', true, Firm::factory()->create()->id));
        $this->actingAs($this->reinaldo);

        contractRefSend($theirs, $this->andrade->id)->assertNotFound();
        contractRefSend(new ClientContract(['id' => 999_999]), $this->andrade->id)->assertNotFound();
    });
});

describe('a Radar or plain referral and a contract referral never block each other (D11, AM11)', function () {
    it('takes a contract referral after a Radar batch sent the same client to the same partner', function () {
        $this->actingAs($this->reinaldo);
        $batch = $this->postJson('/api/radar/referrals', ['rule' => 'contratos', 'partner_id' => $this->margem->id])->assertOk()->json('data');

        contractRefSend($this->contract, $this->margem->id)->assertCreated();

        expect($batch['sent'])->toBe(1)
            ->and(Referral::withoutGlobalScopes()->where('partner_id', $this->margem->id)->pluck('subject_key')->sort()->values()->all())
            ->toBe(['contrato:'.$this->contract->id, 'lead']);
    });

    it('takes the Radar batch after a contract referral, and still skips the client on a second batch', function () {
        $this->actingAs($this->reinaldo);
        contractRefSend($this->contract, $this->margem->id)->assertCreated();

        expect($this->postJson('/api/radar/referrals', ['rule' => 'contratos', 'partner_id' => $this->margem->id])->assertOk()->json('data'))
            ->toBe(['sent' => 1, 'skipped_no_consent' => 0, 'skipped_already' => 0])
            ->and($this->postJson('/api/radar/referrals', ['rule' => 'contratos', 'partner_id' => $this->margem->id])->json('data'))
            ->toBe(['sent' => 0, 'skipped_no_consent' => 0, 'skipped_already' => 1]);
    });

    it('takes a plain referral of the portfolio lead after a contract referral, and still refuses a second plain one', function () {
        $this->actingAs($this->reinaldo);
        $leadId = Referral::withoutGlobalScopes()->findOrFail(contractRefSend($this->contract, $this->andrade->id)->json('data.id'))->lead_id;

        $this->postJson("/api/leads/{$leadId}/referrals", ['partner_id' => $this->andrade->id, 'message' => 'Podem olhar?'])->assertCreated();
        $this->postJson("/api/leads/{$leadId}/referrals", ['partner_id' => $this->andrade->id, 'message' => 'De novo?'])->assertConflict()->assertJsonPath('code', 'already_referred');
    });
});

describe('who may send (R28)', function () {
    it('refuses the team with 403', function () {
        $this->actingAs(FiscalRows::person('equipe'));

        $this->getJson('/api/contracts/legal-partners')->assertForbidden();
        contractRefSend($this->contract, $this->andrade->id)->assertForbidden();
    });

    it('lets the operator send', function () {
        /** @var User $operator */
        $operator = FiscalRows::person('operador');

        $this->actingAs($operator);
        contractRefSend($this->contract, $this->andrade->id)->assertCreated();
    });
});
