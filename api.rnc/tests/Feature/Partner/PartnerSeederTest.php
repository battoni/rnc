<?php

use App\Modules\Lead\Models\LeadConsent;
use App\Modules\Lead\Models\LeadEvent;
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

it('seeds no partner on a production database', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DatabaseSeeder::class)->setContainer(app())->__invoke();

    expect(Partner::withoutGlobalScopes()->count())->toBe(0);
});
