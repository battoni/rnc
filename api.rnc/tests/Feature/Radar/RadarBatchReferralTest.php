<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Diagnostic\Enums\DiagnosticRule;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Helpers\ConsentTerms;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Lead\Repositories\LeadRepository;
use App\Modules\Partner\Mail\ReferralSent;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\Support\FiscalRows;

/*
 * The Radar's batch referral (rnc-2 F10, R27, R28, A21; D13; plan AM10), behind `radar.manage`:
 *
 *   POST /api/radar/referrals   {rule, partner_id} → {sent, skipped_no_consent, skipped_already}
 *
 * The partner must be an active partner of the firm whose specialty covers the rule (else 422
 * `partner_not_covering`). The batch is the rule's clients in the Radar's order (the range's top,
 * ties by id), without those whose `partner_consent` is not true (null = never answered = no,
 * W98) and those already referred to that partner, at most 10. Each goes through the client's ONE
 * portfolio lead (`channel = carteira`, stage cliente, `client_id`, the client's name, CNPJ and
 * profile — AM10), its consent copied `via contrato`; the referral reaches the partner's inbox
 * with the company and Franco's own contact. `GET radar` says, per row and covering partner, how
 * big the next batch is and how many were already sent.
 */
function batchPartner(string $name, string $specialty, array $attributes = [], ?int $firmId = null): Partner
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

function batchLogin(Partner $partner): User
{
    return User::factory()->create([
        'firm_id' => $partner->firm_id,
        'role_id' => Role::where('slug', 'parceiro')->value('id'),
        'partner_id' => $partner->id,
    ]);
}

/** A client the `contratos` rule finds (every profile has it), consented `via contrato` in 2019 unless told otherwise. */
function batchClient(string $name, string $band, ?bool $consent = true, ?int $firmId = null): Client
{
    return FiscalRows::client([
        'name' => $name,
        'sector' => 'servicos',
        'regime' => 'presumido',
        'band' => $band,
        'b2b_share' => 60,
        'partner_consent' => $consent,
        'partner_consent_at' => $consent === null ? null : CarbonImmutable::parse('2019-01-15T12:00:00Z'),
        'partner_consent_via' => $consent === null ? null : 'contrato',
    ], $firmId);
}

function batchSend(int $partnerId, string $rule = 'contratos'): TestResponse
{
    return test()->postJson('/api/radar/referrals', ['rule' => $rule, 'partner_id' => $partnerId]);
}

/** @return array<string, mixed>|null the partner option of `GET radar`'s row */
function batchOption(string $rule, int $partnerId): ?array
{
    $row = collect(test()->getJson('/api/radar')->assertOk()->json('data.types'))->firstWhere('rule', $rule);

    return collect($row['partners'])->firstWhere('id', $partnerId);
}

beforeEach(function () {
    Http::preventStrayRequests();
    Mail::fake();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class]);
    $this->ceo = FiscalRows::person('ceo');

    // Two covering partners for `contratos` (precificação), one that covers nothing, one inactive.
    $this->margem = batchPartner('Margem Certa (exemplo)', 'precificacao_controladoria');
    $this->margem2 = batchPartner('Margem Dois (exemplo)', 'precificacao_controladoria');
    $this->seguros = batchPartner('Amparo Seguros (exemplo)', 'seguros_beneficios');
    $this->inactive = batchPartner('Margem Antiga (exemplo)', 'precificacao_controladoria', ['active' => false]);

    // b4 first (the biggest range), then twelve b3 tied, by id. Two big ones without consent.
    $this->big = batchClient('Grande Consentida Ltda', 'b4');
    $this->refused = batchClient('Grande Recusou Ltda', 'b4', false);
    $this->unanswered = batchClient('Grande Sem Resposta Ltda', 'b4', null);
    $this->tied = collect(range(1, 12))->map(fn (int $n) => batchClient(sprintf('Empate %02d Ltda', $n), 'b3'));

    // Another firm's consented client and partner: never in this firm's batch.
    $this->other = Firm::factory()->create();
    $this->foreignClient = batchClient('Outra Firma Ltda', 'b4', true, $this->other->id);
    $this->foreignPartner = batchPartner('Parceiro Alheio', 'precificacao_controladoria', [], $this->other->id);
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('the batch (R27, A21)', function () {
    it('sends at most 10, the largest first, skipping clients without consent (A21)', function () {
        $answer = $this->actingAs($this->ceo)->postJson('/api/radar/referrals', ['rule' => 'contratos', 'partner_id' => $this->margem->id])
            ->assertOk()->json('data');

        $sentTo = Referral::withoutGlobalScopes()->where('partner_id', $this->margem->id)->with('lead')->get()->pluck('lead.client_id')->sort()->values()->all();
        $expected = collect([$this->big->id, ...$this->tied->take(9)->pluck('id')])->sort()->values()->all();

        expect($answer)->toBe(['sent' => 10, 'skipped_no_consent' => 2, 'skipped_already' => 0])
            ->and($sentTo)->toBe($expected)
            ->and($sentTo)->not->toContain($this->refused->id, $this->unanswered->id, $this->foreignClient->id);
        Mail::assertQueuedCount(10);
        Mail::assertQueued(ReferralSent::class, fn (ReferralSent $mail) => $mail->hasTo($this->margem->email));
    });

    it('skips the clients already referred to that partner; a second identical batch sends 0 (A21)', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id)->assertOk();

        expect(batchSend($this->margem->id)->assertOk()->json('data'))->toBe(['sent' => 3, 'skipped_no_consent' => 2, 'skipped_already' => 10])
            ->and(batchSend($this->margem->id)->assertOk()->json('data'))->toBe(['sent' => 0, 'skipped_no_consent' => 2, 'skipped_already' => 13])
            ->and(Referral::withoutGlobalScopes()->where('partner_id', $this->margem->id)->count())->toBe(13);
    });

    it('treats a consent never answered (null) as no, like `false` (W98)', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id);
        batchSend($this->margem->id);

        $leadClients = Lead::withoutGlobalScopes()->whereNotNull('client_id')->pluck('client_id')->all();

        expect($leadClients)->not->toContain($this->refused->id)
            ->and($leadClients)->not->toContain($this->unanswered->id);
    });

    it('reuses the client\'s one portfolio lead when a second partner gets the same client', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id);

        expect(batchSend($this->margem2->id)->assertOk()->json('data.sent'))->toBe(10)
            ->and(Lead::withoutGlobalScopes()->where('client_id', $this->big->id)->count())->toBe(1)
            ->and(Referral::withoutGlobalScopes()->whereHas('lead', fn ($lead) => $lead->withoutGlobalScopes()->where('client_id', $this->big->id))->count())->toBe(2);
    });

    it('reuses the lead a racing batch created after this one looked (the unique firm + client)', function () {
        // Another batch committed this client's `carteira` lead after this one read "none".
        $raced = Lead::factory()->create(['firm_id' => FiscalRows::francoId(), 'stage' => 'cliente', 'channel' => 'carteira', 'client_id' => $this->big->id]);
        $this->partialMock(LeadRepository::class, fn ($mock) => $mock->shouldReceive('findPortfolioLeadOfClient')->andReturnNull());

        expect($this->actingAs($this->ceo)->postJson('/api/radar/referrals', ['rule' => 'contratos', 'partner_id' => $this->margem->id])->assertOk()->json('data.sent'))->toBe(10)
            ->and(Lead::withoutGlobalScopes()->where('client_id', $this->big->id)->sole()->id)->toBe($raced->id)
            ->and($raced->consents()->sole()->via)->toBe('contrato')
            ->and(Referral::withoutGlobalScopes()->where('lead_id', $raced->id)->sole()->partner_id)->toBe($this->margem->id);
        Mail::assertQueuedCount(10);
    });
});

describe('the portfolio lead and the referral (D13, AM10, A21)', function () {
    it('creates a `carteira` lead at stage cliente with the client\'s name, CNPJ and profile and no contact (AM10)', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id);

        $lead = Lead::withoutGlobalScopes()->where('client_id', $this->big->id)->sole();

        expect($lead->channel)->toBe('carteira')
            ->and($lead->stage)->toBe('cliente')
            ->and($lead->firm_id)->toBe(FiscalRows::francoId())
            ->and($lead->company)->toBe('Grande Consentida Ltda')
            ->and($lead->cnpj)->toBe($this->big->cnpj)
            ->and($lead->profile)->toMatchArray(['sector' => 'servicos', 'regime' => 'presumido', 'band' => 'b4', 'b2b' => 60])
            ->and([$lead->contact_name, $lead->phone, $lead->email])->toBe([null, null, null])
            ->and($lead->token_expires_at->isFuture())->toBeFalse();
    });

    it('copies the client\'s consent `via contrato` onto the lead and the referral (R27, A21)', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id);

        $lead = Lead::withoutGlobalScopes()->where('client_id', $this->big->id)->sole();
        $consent = $lead->latestConsent;
        $referral = Referral::withoutGlobalScopes()->where('lead_id', $lead->id)->sole();

        expect($consent->given)->toBeTrue()
            ->and($consent->via)->toBe('contrato')
            ->and($consent->text)->toBe(ConsentTerms::CONTRACT_TEXT)
            ->and($consent->given_at->utc()->toIso8601String())->toBe('2019-01-15T12:00:00+00:00')
            ->and($referral->consent_text)->toBe(ConsentTerms::CONTRACT_TEXT)
            ->and($referral->consent_given_at->utc()->toIso8601String())->toBe('2019-01-15T12:00:00+00:00');
    });

    it('gives an existing `carteira` lead with no consent row the contract\'s before referring it', function () {
        $this->actingAs($this->ceo);
        $lead = Lead::factory()->create(['firm_id' => FiscalRows::francoId(), 'stage' => 'cliente', 'channel' => 'carteira', 'client_id' => $this->big->id, 'company' => 'Nome Antigo']);

        batchSend($this->margem->id)->assertOk();

        expect($lead->fresh()->company)->toBe('Grande Consentida Ltda')
            ->and($lead->consents()->sole()->via)->toBe('contrato')
            ->and(Referral::withoutGlobalScopes()->where('lead_id', $lead->id)->sole()->consent_text)->toBe(ConsentTerms::CONTRACT_TEXT);
    });

    it('scores the referral on the client\'s profile, covers the rule and writes the demo\'s Radar message', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id);

        $referral = Referral::withoutGlobalScopes()->whereHas('lead', fn ($lead) => $lead->withoutGlobalScopes()->where('client_id', $this->big->id))->sole();
        $title = DiagnosticRule::Contratos->title();

        expect($referral->status)->toBe('enviado')
            ->and($referral->referred_by)->toBe($this->ceo->id)
            ->and(array_column($referral->covers, 'id'))->toContain('contratos')
            ->and($referral->score)->toBeGreaterThanOrEqual(70)
            ->and($referral->message)->toBe("A Franco Contabilidade indica a Grande Consentida Ltda, cliente da carteira desde 2019: o Radar da Reforma apontou “{$title}”. Podem fazer o primeiro contato em até 24 horas úteis? A Franco Contabilidade segue responsável pelo cliente.");
    });

    it('writes one `encaminhamento.enviado` line on each lead\'s timeline, by the sender', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id);

        $lines = LeadEvent::query()->where('type', 'encaminhamento.enviado')->get();

        expect($lines)->toHaveCount(10)
            ->and($lines->pluck('user_id')->unique()->all())->toBe([$this->ceo->id])
            ->and($lines->pluck('data.partner_id')->unique()->all())->toBe([$this->margem->id]);
    });

    it('reaches the partner\'s inbox with the company set and Franco\'s contact once accepted (AM10, A21)', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id);
        $login = batchLogin($this->margem);

        $inbox = collect($this->actingAs($login)->getJson('/api/portal/referrals')->assertOk()->json('data'));
        $big = $inbox->firstWhere('company', 'Grande Consentida Ltda');

        expect($inbox)->toHaveCount(10)
            ->and($inbox->pluck('company')->filter()->count())->toBe(10)
            ->and($big['profile'])->toBe(['sector' => 'servicos', 'regime' => 'presumido', 'band' => 'b4'])
            ->and($big['contact'])->toBeNull();

        $accepted = $this->actingAs($login)->postJson("/api/portal/referrals/{$big['id']}/transition", ['to' => 'aceito'])->assertOk()->json('data');

        expect($accepted['contact'])->toBe(['via' => 'firm', 'name' => 'Franco Contabilidade', 'phone' => '11 3732-5500', 'email' => 'saf@franco-rnc.com.br']);
    });
});

describe('who may be sent a batch (R27, A21)', function () {
    it('refuses a partner whose specialty does not cover the rule — 422 `partner_not_covering`, nothing written', function () {
        $this->actingAs($this->ceo)->postJson('/api/radar/referrals', ['rule' => 'contratos', 'partner_id' => $this->seguros->id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'partner_not_covering');

        expect(Referral::withoutGlobalScopes()->count())->toBe(0)
            ->and(Lead::withoutGlobalScopes()->count())->toBe(0);
        Mail::assertNothingQueued();
    });

    it('refuses an inactive partner and another firm\'s partner (422 on `partner_id`)', function (string $which) {
        $this->actingAs($this->ceo)->postJson('/api/radar/referrals', ['rule' => 'contratos', 'partner_id' => $this->{$which}->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('partner_id');

        expect(Referral::withoutGlobalScopes()->count())->toBe(0);
    })->with(['inactive', 'foreignPartner']);

    it('refuses an unknown rule (422 on `rule`)', function () {
        $this->actingAs($this->ceo)->postJson('/api/radar/referrals', ['rule' => 'nao-existe', 'partner_id' => $this->margem->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rule');
    });

    it('answers 401 to a guest and 403 to the team (radar.manage)', function () {
        batchSend($this->margem->id)->assertUnauthorized();

        $this->actingAs(FiscalRows::person('equipe'));
        batchSend($this->margem->id)->assertForbidden();

        expect(Referral::withoutGlobalScopes()->count())->toBe(0);
    });

    it('never batches another firm\'s client, and the other firm\'s partner sees nothing', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id);
        batchSend($this->margem->id);

        $foreignLogin = batchLogin($this->foreignPartner);

        expect(Lead::withoutGlobalScopes()->where('client_id', $this->foreignClient->id)->exists())->toBeFalse();
        $this->actingAs($foreignLogin)->getJson('/api/portal/referrals')->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('the row\'s partner picker (R27)', function () {
    it('offers only the active covering partners, with the next batch and the already sent (A21)', function () {
        $this->actingAs($this->ceo);
        $row = collect($this->getJson('/api/radar')->assertOk()->json('data.types'))->firstWhere('rule', 'contratos');

        expect(collect($row['partners'])->pluck('id')->all())->toBe([$this->margem->id, $this->margem2->id])
            ->and($row['partners'][0])->toBe(['id' => $this->margem->id, 'name' => 'Margem Certa (exemplo)', 'specialty' => 'precificacao_controladoria', 'batch' => 10, 'sent' => 0])
            ->and($row['without_consent'])->toBe(2);
    });

    it('counts down to "Todos já encaminhados": batch 10 → 3 → 0 (A21)', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id);

        expect(batchOption('contratos', $this->margem->id))->toMatchArray(['batch' => 3, 'sent' => 10])
            ->and(batchOption('contratos', $this->margem2->id))->toMatchArray(['batch' => 10, 'sent' => 0]);

        batchSend($this->margem->id);

        expect(batchOption('contratos', $this->margem->id))->toMatchArray(['batch' => 0, 'sent' => 13]);
    });

    it('carries the same options on the type\'s detail', function () {
        $this->actingAs($this->ceo);
        batchSend($this->margem->id);

        $type = $this->getJson('/api/radar/types/contratos')->assertOk()->json('data.type');

        expect(collect($type['partners'])->firstWhere('id', $this->margem->id))->toMatchArray(['batch' => 3, 'sent' => 10]);
    });

    it('offers on every row only the active partners whose specialty covers its rule', function () {
        $this->actingAs($this->ceo);
        $rows = $this->getJson('/api/radar')->assertOk()->json('data.types');
        $network = [$this->margem, $this->margem2, $this->seguros];

        foreach ($rows as $row) {
            $rule = DiagnosticRule::from($row['rule']);
            $covering = collect($network)->filter(fn (Partner $partner) => in_array($rule, $partner->specialtyCase()->covers(), true))->pluck('id')->sort()->values()->all();

            expect(collect($row['partners'])->pluck('id')->sort()->values()->all())->toBe($covering, $row['rule']);
        }
    });
});

describe('the cost (plan AM6)', function () {
    it('keeps `GET radar` and the batch at a fixed number of queries once leads and referrals exist', function () {
        $this->actingAs($this->ceo);
        $count = function (callable $call) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $call();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };
        batchSend($this->margem->id);
        $summary = $count(fn () => $this->getJson('/api/radar')->assertOk());

        foreach (range(1, 20) as $index) {
            batchClient(sprintf('Mais %02d Ltda', $index), 'b2');
        }

        batchSend($this->margem2->id);

        expect($count(fn () => $this->getJson('/api/radar')->assertOk()))->toBe($summary)
            ->and($summary)->toBeLessThanOrEqual(8);
    });
});
