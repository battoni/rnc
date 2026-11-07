<?php

use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Partner\Helpers\BusinessHours;
use App\Modules\Partner\Models\Partner;
use App\Modules\Partner\Models\Referral;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

/*
 * The local network (rnc-1 F6, R21): the demo's eight invented partners, one per specialty of
 * facts.md §3, each marked "(exemplo)" with an invented address — and a few referrals in the trail,
 * only for seeded leads that said yes. Never on production.
 */
beforeEach(fn () => CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo')));

afterEach(fn () => CarbonImmutable::setTestNow());

it('seeds eight placeholder partners, one per specialty', function () {
    $this->seed();

    $partners = Partner::withoutGlobalScopes()->get();

    expect($partners)->toHaveCount(8)
        ->and($partners->pluck('specialty')->unique())->toHaveCount(8)
        ->and($partners->every(fn (Partner $partner) => str_ends_with($partner->name, '(exemplo)')))->toBeTrue()
        ->and($partners->every(fn (Partner $partner) => str_ends_with($partner->email, '@parceiro.exemplo')))->toBeTrue()
        ->and($partners->pluck('firm_id')->unique()->all())->toBe([1]);
});

it('fills the trail only with leads that gave consent, each with its timeline line', function () {
    $this->seed();

    $referrals = Referral::withoutGlobalScopes()->get();
    $consenting = LeadConsent::query()->where('given', true)->pluck('lead_id');

    expect($referrals->count())->toBeGreaterThanOrEqual(4)
        ->and($referrals->every(fn (Referral $referral) => $consenting->contains($referral->lead_id)))->toBeTrue()
        ->and(LeadEvent::query()->where('type', 'encaminhamento.enviado')->count())->toBe($referrals->count())
        ->and($referrals->pluck('partner_id')->unique()->count())->toBeGreaterThanOrEqual(2);
});

it('seeds the demo\'s twelve referrals in every status, open ones on the SLA clock (rnc-2 F2)', function () {
    $this->seed();

    $referrals = Referral::withoutGlobalScopes()->orderBy('id')->get();
    $open = $referrals->where('status', 'enviado')->values();
    $week = new BusinessHours(Firm::where('slug', 'franco')->value('business_hours'));
    $now = CarbonImmutable::now();

    expect($referrals->pluck('status')->all())->toBe(['fechado', 'contato', 'enviado', 'proposta', 'fechado', 'enviado', 'reuniao', 'aceito', 'fechado', 'recusado', 'enviado', 'fechado'])
        ->and($referrals->pluck('lead_id')->unique())->toHaveCount(12)
        ->and($open->map(fn (Referral $referral) => round($week->between($referral->created_at, $now)))->all())->toBe([3.0, 15.0, 30.0])
        ->and($referrals->whereIn('status', ['contato', 'reuniao', 'proposta', 'fechado'])->every(fn (Referral $referral) => $referral->contacted_at !== null && $referral->contacted_at > $referral->created_at))->toBeTrue()
        ->and($referrals->whereNotNull('contacted_at')->every(fn (Referral $referral) => ($hours = $week->between($referral->created_at, $referral->contacted_at)) >= 2 && $hours <= 7))->toBeTrue()
        ->and($referrals->whereIn('status', ['enviado', 'aceito', 'recusado'])->every(fn (Referral $referral) => $referral->contacted_at === null))->toBeTrue()
        ->and($referrals->firstWhere('status', 'reuniao')->meeting_at > $now)->toBeTrue()
        ->and($referrals->where('status', 'fechado')->every(fn (Referral $referral) => $referral->deal_value_cents > 0 && $referral->closed_at !== null))->toBeTrue()
        ->and($referrals->where('status', '!=', 'enviado')->every(fn (Referral $referral) => $referral->status_at !== null && $referral->status_at <= $now))->toBeTrue();
});

it('prices each seeded closing by the lead\'s revenue band, the demo\'s dealHint', function () {
    $this->seed();

    $bands = ['b1' => 600_000, 'b2' => 1_200_000, 'b3' => 2_400_000, 'b4' => 4_800_000, 'b5' => 9_600_000];
    $closed = Referral::withoutGlobalScopes()->with(['lead' => fn ($query) => $query->withoutGlobalScopes()])->where('status', 'fechado')->get();

    expect($closed)->toHaveCount(4)
        ->and($closed->every(fn (Referral $referral) => $referral->deal_value_cents === ($bands[$referral->lead->profile['band'] ?? ''] ?? 1_200_000)))->toBeTrue();
});

it('writes one encaminhamento.status line per step each seeded referral took', function () {
    $this->seed();

    $steps = ['enviado' => 0, 'aceito' => 1, 'contato' => 2, 'reuniao' => 3, 'proposta' => 4, 'fechado' => 5, 'recusado' => 1];
    $referrals = Referral::withoutGlobalScopes()->get();
    $lines = LeadEvent::query()->where('type', 'encaminhamento.status')->get()->groupBy('data.referral_id');

    foreach ($referrals as $referral) {
        $mine = $lines->get($referral->id, collect());

        expect($mine)->toHaveCount($steps[$referral->status])
            ->and($mine->isEmpty() || $mine->sortBy('at')->last()->data['to'] === $referral->status)->toBeTrue();
    }
});

it('seeds no partner on a production database', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DatabaseSeeder::class)->setContainer(app())->__invoke();

    expect(Partner::withoutGlobalScopes()->count())->toBe(0);
});
