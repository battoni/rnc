<?php

use App\Models\User;
use App\Modules\Lead\Models\Lead;
use App\Modules\Tracking\Models\TrackingEvent;
use App\Modules\Tracking\Models\Visitor;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;

/*
 * The local traffic (rnc-1 F7, R24, A4): 30 days of invented visits and CTA clicks, and the beacons
 * of every seeded lead's own walk (F5) — so the funnel on a fresh seed is credible, 7 days differ
 * from 30, and the visitors of the leads are the same people the pipeline shows. Never on production.
 */
beforeEach(fn () => CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo')));

afterEach(fn () => CarbonImmutable::setTestNow());

function seededFunnel(int $days): array
{
    $ceo = User::query()->where('username', 'reinaldo')->firstOrFail();

    return array_column(test()->actingAs($ceo)->getJson("/api/metrics/funnel?days={$days}")->assertOk()->json('data.steps'), 'count', 'key');
}

it('gives every seeded lead a visitor with its own walk, by its token\'s lead id', function () {
    $this->seed();

    $leads = Lead::withoutGlobalScopes()->get();
    $visitors = Visitor::withoutGlobalScopes()->whereIn('uuid', $leads->pluck('visitor_uuid'))->get()->keyBy('uuid');

    expect($visitors)->toHaveCount($leads->count())
        ->and($leads->every(fn (Lead $lead) => $visitors[$lead->visitor_uuid]->source === $lead->source))->toBeTrue()
        ->and(TrackingEvent::withoutGlobalScopes()->where('type', 'diag.start')->whereNotNull('lead_id')->count())->toBe($leads->count());
});

it('fills 30 days so the funnel narrows step by step and 7 days differ from 30', function () {
    $this->seed();

    $week = seededFunnel(7);
    $month = seededFunnel(30);

    foreach ([$week, $month] as $counts) {
        $values = array_values($counts);

        foreach (array_slice($values, 1) as $position => $count) {
            expect($count)->toBeLessThanOrEqual($values[$position]);
        }
    }

    expect($month['visitas'])->toBeGreaterThan(500)
        ->and($week['visitas'])->toBeGreaterThan(100)
        ->and($week['visitas'])->toBeLessThan($month['visitas'])
        ->and($month['agendou'])->toBeGreaterThan($week['agendou'])
        ->and($month['cliente'])->toBeGreaterThanOrEqual(1)
        ->and(TrackingEvent::withoutGlobalScopes()->where('at', '>', CarbonImmutable::now())->count())->toBe(0);
});

it('seeds no traffic on a production database', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DatabaseSeeder::class)->setContainer(app())->__invoke();

    expect(Visitor::withoutGlobalScopes()->count())->toBe(0)
        ->and(TrackingEvent::withoutGlobalScopes()->count())->toBe(0);
});
