<?php

use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialistSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/*
 * The "Relatório de Impacto" (rnc-1 R17, plan AM4, AM15): `GET /api/public/reports/{report_token}`,
 * the token made at booking and valid 30 days. The company, the result (titles and ranges, no
 * detail), the 2026–2033 simulation and the meeting — never the lead's contact.
 */
function reportedLead(array $overrides = [], string $local = '2026-11-24 14:30'): Lead
{
    $franco = Firm::where('slug', 'franco')->firstOrFail();
    $lead = Lead::factory()->create(array_merge([
        'firm_id' => $franco->id,
        'stage' => 'reuniao_marcada',
        'company' => 'Metalúrgica Vale',
        'contact_name' => 'Bruno Lima',
        'phone' => '5511977776666',
        'email' => 'bruno@metalurgicavale.com.br',
        'profile' => ['sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 80, 'incentivos' => false, 'exporta' => false],
        'report_token' => Str::random(48),
        'report_expires_at' => now()->addDays(30),
    ], $overrides));

    $specialist = Specialist::withoutGlobalScopes()->whereHas('user', fn ($query) => $query->where('username', 'juliana.reis'))->sole();

    Appointment::withoutGlobalScopes()->create([
        'firm_id' => $franco->id,
        'lead_id' => $lead->id,
        'specialist_id' => $specialist->id,
        'starts_at' => CarbonImmutable::parse($local, 'America/Sao_Paulo'),
        'ends_at' => CarbonImmutable::parse($local, 'America/Sao_Paulo')->addMinutes(30),
    ]);

    return $lead;
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class, SpecialistSeeder::class]);
});

it('shows the company, the locked result, the simulation and the meeting', function () {
    $lead = reportedLead();

    $report = $this->getJson("/api/public/reports/{$lead->report_token}")->assertOk()->json('data');

    expect($report['company'])->toBe('Metalúrgica Vale')
        ->and($report['profile'])->toMatchArray(['sector' => 'servicos', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 80])
        ->and($report['result']['opportunities'])->toBeGreaterThanOrEqual(1)
        ->and($report['result']['items'][0])->toHaveKeys(['kind', 'title', 'range_min', 'range_max', 'range_label'])
        ->and($report['result']['items'][0])->not->toHaveKeys(['detail', 'rule'])
        ->and($report['simulation']['years'])->toHaveCount(8)
        ->and($report['simulation']['estimate'])->toBeTrue()
        ->and($report['meeting'])->toBe([
            'specialist' => 'Juliana Reis',
            'area' => 'Contábil · Simples Nacional',
            'starts_at' => '2026-11-24T14:30:00-03:00',
            'ends_at' => '2026-11-24T15:00:00-03:00',
        ]);
});

it('never carries the lead contact, its tokens or its id', function () {
    $lead = reportedLead();

    $json = $this->getJson("/api/public/reports/{$lead->report_token}")->assertOk()->json();
    $flat = json_encode($json);

    expect($flat)->not->toContain('97777')
        ->not->toContain('metalurgicavale.com.br')
        ->not->toContain('Bruno Lima')
        ->not->toContain($lead->token)
        ->and(Arr::dot($json))->not->toHaveKeys(['data.id', 'data.email', 'data.phone', 'data.token', 'data.report_token']);
});

it('answers 404 for an unknown, an expired or a diagnostic token', function () {
    $lead = reportedLead();
    $expired = reportedLead(['report_expires_at' => now()->subMinute()], '2026-11-25 09:00');

    $this->getJson('/api/public/reports/'.str_repeat('x', 48))->assertNotFound();
    $this->getJson("/api/public/reports/{$expired->report_token}")->assertNotFound();
    $this->getJson("/api/public/reports/{$lead->token}")->assertNotFound();
});
