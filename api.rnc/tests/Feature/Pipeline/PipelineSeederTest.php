<?php

use App\Modules\Booking\Models\Appointment;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

/*
 * The local pipeline (rnc-1 F5): about 40 invented leads across every stage, 30 days of history and
 * a few booked meetings, so the board and the agenda are not empty. Never on production.
 */
beforeEach(fn () => CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo')));

afterEach(fn () => CarbonImmutable::setTestNow());

it('fills every stage with invented leads from the last 30 days', function () {
    $this->seed();

    $leads = Lead::withoutGlobalScopes()->get();

    expect($leads->count())->toBeGreaterThanOrEqual(36)->toBeLessThanOrEqual(45)
        ->and($leads->pluck('stage')->unique()->sort()->values()->all())
        ->toBe(['cliente', 'lead', 'lead_parcial', 'perdido', 'proposta', 'reuniao_marcada', 'visitante'])
        ->and($leads->min('created_at')->greaterThanOrEqualTo(now()->subDays(31)))->toBeTrue()
        ->and($leads->where('stage', 'perdido')->whereNull('lost_reason'))->toBeEmpty()
        ->and($leads->pluck('firm_id')->unique()->all())->toBe([1]);
});

it('gives each lead a timeline and books the meetings of the reunião marcada column', function () {
    $this->seed();

    $booked = Lead::withoutGlobalScopes()->where('stage', 'reuniao_marcada')->pluck('id');

    expect(LeadEvent::query()->distinct()->count('lead_id'))->toBe(Lead::withoutGlobalScopes()->count())
        ->and(LeadEvent::query()->where('type', 'etapa.alterada')->whereNotNull('user_id')->exists())->toBeTrue()
        ->and(LeadEvent::query()->where('type', 'nota.adicionada')->exists())->toBeTrue()
        ->and($booked->count())->toBeGreaterThanOrEqual(3)
        ->and(Appointment::withoutGlobalScopes()->whereIn('lead_id', $booked)->count())->toBe($booked->count());
});

it('uses only obviously invented phones', function () {
    $this->seed();

    $phones = Lead::withoutGlobalScopes()->whereNotNull('phone')->pluck('phone');

    expect($phones)->not->toBeEmpty()
        ->and($phones->every(fn (string $phone) => str_starts_with($phone, '55119000')))->toBeTrue();
});

it('seeds no lead on a production database', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DatabaseSeeder::class)->setContainer(app())->__invoke();

    expect(Lead::withoutGlobalScopes()->count())->toBe(0);
});
