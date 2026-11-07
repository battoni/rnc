<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialistSeeder;

/*
 * The team's agenda (rnc-1 F5, R20; plan AM8, AM9): `GET /api/agenda?week=YYYY-MM-DD` answers the
 * working week (Monday to Friday, São Paulo) that holds the date, with every booked meeting of the
 * firm, and the dates of the weeks before and after for ◀ ▶. Behind `agenda.read`.
 *
 * The clock is Thursday 19/11/2026 10:00 in São Paulo: the current week runs 16/11 to 20/11.
 */
const AGENDA_ZONE = 'America/Sao_Paulo';

function agendaMeeting(Firm $firm, string $company, string $localStart, ?Specialist $specialist = null): Appointment
{
    $lead = Lead::factory()->create(['firm_id' => $firm->id, 'stage' => 'reuniao_marcada', 'company' => $company]);
    $start = CarbonImmutable::parse($localStart, AGENDA_ZONE);

    return Appointment::withoutGlobalScopes()->create([
        'firm_id' => $firm->id,
        'lead_id' => $lead->id,
        'specialist_id' => ($specialist ?? Specialist::withoutGlobalScopes()->where('firm_id', $firm->id)->firstOrFail())->id,
        'starts_at' => $start,
        'ends_at' => $start->addMinutes(30),
    ]);
}

function agendaReader(): User
{
    return User::where('username', 'patricia')->firstOrFail();
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', AGENDA_ZONE));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class, SpecialistSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('answers the current working week with its meetings, by day and hour', function () {
    $mariana = Specialist::withoutGlobalScopes()->whereHas('user', fn ($query) => $query->where('username', 'mariana.toledo'))->sole();
    agendaMeeting($this->franco, 'Clínica Tarde', '2026-11-18 15:00', $mariana);
    agendaMeeting($this->franco, 'Padaria Manhã', '2026-11-18 08:30', $mariana);
    agendaMeeting($this->franco, 'Oficina Segunda', '2026-11-16 08:00');
    agendaMeeting($this->franco, 'Semana que vem', '2026-11-23 09:00');

    $agenda = $this->actingAs(agendaReader())->getJson('/api/agenda')->assertOk()->json('data');

    expect($agenda)->toMatchArray([
        'week_start' => '2026-11-16',
        'week_end' => '2026-11-20',
        'previous_week' => '2026-11-09',
        'next_week' => '2026-11-23',
    ])
        ->and(array_column($agenda['days'], 'date'))->toBe(['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20'])
        ->and(array_column($agenda['days'][2]['meetings'], 'company'))->toBe(['Padaria Manhã', 'Clínica Tarde'])
        ->and($agenda['days'][2]['meetings'][0])->toMatchArray([
            'starts_at' => '2026-11-18T08:30:00-03:00',
            'ends_at' => '2026-11-18T09:00:00-03:00',
            'specialist' => 'Mariana Toledo',
            'stage' => 'reuniao_marcada',
        ])
        ->and($agenda['days'][2]['meetings'][0]['lead_id'])->toBeInt()
        ->and(array_column($agenda['days'][0]['meetings'], 'company'))->toBe(['Oficina Segunda'])
        ->and($agenda['days'][3]['meetings'])->toBe([]);
});

it('moves to the week that holds the date asked', function () {
    agendaMeeting($this->franco, 'Semana que vem', '2026-11-27 16:30');

    $agenda = $this->actingAs(agendaReader())->getJson('/api/agenda?week=2026-11-25')->assertOk()->json('data');

    expect($agenda['week_start'])->toBe('2026-11-23')
        ->and($agenda['previous_week'])->toBe('2026-11-16')
        ->and(array_column($agenda['days'][4]['meetings'], 'company'))->toBe(['Semana que vem']);
});

it('never shows another firm\'s meetings', function () {
    $other = Firm::factory()->create();
    $person = User::factory()->create(['firm_id' => $other->id, 'role_id' => Role::where('slug', 'equipe')->value('id'), 'is_specialist' => true]);
    $specialist = Specialist::withoutGlobalScopes()->create(['firm_id' => $other->id, 'user_id' => $person->id]);
    agendaMeeting($other, 'De outra firma', '2026-11-18 10:00', $specialist);

    $agenda = $this->actingAs(agendaReader())->getJson('/api/agenda')->assertOk()->json('data');

    expect(collect($agenda['days'])->flatMap(fn (array $day) => $day['meetings']))->toBeEmpty();
});

it('opens on the coming week on a weekend, when no week is asked', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-21 10:00', AGENDA_ZONE));

    expect($this->actingAs(agendaReader())->getJson('/api/agenda')->assertOk()->json('data.week_start'))->toBe('2026-11-23')
        ->and($this->actingAs(agendaReader())->getJson('/api/agenda?week=2026-11-21')->assertOk()->json('data.week_start'))->toBe('2026-11-16');
});

it('refuses a week that is not a date', function () {
    $this->actingAs(agendaReader())->getJson('/api/agenda?week=amanha')->assertUnprocessable()->assertJsonValidationErrors('week');
});

it('needs a session and agenda.read', function () {
    $this->getJson('/api/agenda')->assertUnauthorized();

    $this->actingAs(User::factory()->create(['firm_id' => $this->franco->id]))->getJson('/api/agenda')->assertForbidden();
});
