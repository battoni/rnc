<?php

use App\Models\User;
use App\Modules\Briefing\DTOs\BriefingMailDTO;
use App\Modules\Briefing\Mail\DailyBriefingMail;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Services\MailBrand;
use App\Modules\Lead\Models\Lead;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use App\Modules\Portfolio\Models\Client;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;

/*
 * A20 (rnc-4 R19): the briefing reads other modules and degrades when they are off, never a 500.
 * With `briefing` on and `portfolio`, `partners`, `radar` and `contracts` off, `GET briefing` is 200:
 * `referrals` is null (partners), the pipeline has no fee range (portfolio — as an empty portfolio
 * already answers), and no action of the SLA or the Radar kind is picked — even though the rows
 * those modules wrote while they were on are still in the database. The 07:00 e-mail renders the
 * same payload.
 */
const DEGRADE_ZONE = 'America/Sao_Paulo';

/** The rows of a firm that had every module: a breached referral, a fee'd portfolio, a proposal. */
function degradeRows(Firm $firm): void
{
    $partner = Partner::withoutGlobalScopes()->create([
        'firm_id' => $firm->id, 'name' => 'Parceiro Exemplo', 'specialty' => 'advocacia_tributaria',
        'email' => 'parceiro@parceiro.exemplo', 'active' => true,
    ]);
    $sent = CarbonImmutable::parse('2026-11-16 09:00', DEGRADE_ZONE);
    Referral::withoutGlobalScopes()->forceCreate([
        'firm_id' => $firm->id,
        'lead_id' => Lead::factory()->create(['firm_id' => $firm->id, 'stage' => 'cliente', 'company' => 'Indicada Ltda'])->id,
        'partner_id' => $partner->id,
        'referred_by' => User::where('username', 'reinaldo')->value('id'),
        'message' => 'Indicação de teste.',
        'score' => 80,
        'covers' => [],
        'consent_text' => 'x',
        'consent_version' => 1,
        'consent_given_at' => $sent,
        'status' => 'enviado',
        'created_at' => $sent,
        'updated_at' => $sent,
    ]);

    foreach (range(1, 6) as $index) {
        Client::withoutGlobalScopes()->create([
            'firm_id' => $firm->id, 'name' => "Cliente {$index}", 'cnpj' => str_pad((string) $index, 14, '0', STR_PAD_LEFT),
            'sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2', 'b2b_share' => 80, 'monthly_fee' => '1000.00',
            'partner_consent' => true,
        ]);
    }

    Lead::factory()->create([
        'firm_id' => $firm->id, 'stage' => 'proposta',
        'profile' => ['sector' => 'servicos', 'regime' => 'presumido', 'band' => 'b2', 'b2b' => null, 'incentivos' => false, 'exporta' => false],
    ]);
}

function degradeBriefing(): array
{
    return test()->actingAs(User::where('username', 'reinaldo')->firstOrFail())->getJson('/api/briefing')->assertOk()->json('data');
}

/** @return list<string> */
function degradeActionKinds(array $briefing): array
{
    return array_column($briefing['actions'], 'kind');
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', DEGRADE_ZONE));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    degradeRows($this->franco);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('reads the referrals, the fee range and the SLA and Radar actions while every module is on', function () {
    $this->franco->forceFill(['modules' => FirmModules::ALL])->save();

    $briefing = degradeBriefing();

    expect($briefing['referrals']['count'])->toBe(1)
        ->and($briefing['pipeline']['range_cents'])->not->toBeNull()
        ->and(degradeActionKinds($briefing))->toContain('sla_breached');
});

it('answers 200 with referrals null, no fee range and no SLA or Radar action when only briefing is on (A20)', function () {
    $this->franco->forceFill(['modules' => ['briefing']])->save();

    $briefing = degradeBriefing();

    expect($briefing['referrals'])->toBeNull()
        ->and($briefing['pipeline']['range_cents'])->toBeNull()
        ->and(array_column($briefing['pipeline']['stages'], 'range_cents'))->each->toBeNull()
        ->and($briefing['pipeline']['count'])->toBe(1)
        ->and(degradeActionKinds($briefing))->not->toContain('sla_breached')
        ->and(degradeActionKinds($briefing))->not->toContain('radar')
        ->and(degradeActionKinds($briefing))->toContain('proposals');
});

it('keeps the fee range but drops the Radar action with portfolio on and radar off', function () {
    $this->franco->forceFill(['modules' => ['portfolio', 'briefing']])->save();

    $briefing = degradeBriefing();

    expect($briefing['pipeline']['range_cents'])->not->toBeNull()
        ->and(degradeActionKinds($briefing))->not->toContain('radar');
});

it('renders the 07:00 e-mail from a payload without referrals', function () {
    $this->franco->forceFill(['modules' => ['briefing']])->save();
    app()->setLocale('pt_BR');
    $briefing = degradeBriefing();

    $html = (new DailyBriefingMail(new BriefingMailDTO('Reinaldo', 'Franco Contabilidade', 'http://franco.localhost:5173', $briefing, MailBrand::fromFirm($this->franco))))->render();

    expect($html)->toContain('Reinaldo')
        ->and($html)->not->toContain(__('mail.briefing.kpi.referrals'));
});
