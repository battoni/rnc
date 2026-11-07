<?php

use App\Models\User;
use App\Modules\Booking\Mail\MeetingReminder;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/*
 * The reminder (rnc-1 R17, A10; plan AM6): `appointments:remind` sends the 24-hour reminder for
 * every meeting that starts within the next 24 hours, once — `reminded_at` is the guard. It walks
 * every firm with that firm as the current one, so each firm's meetings are found and none leaks.
 */
function remindableMeeting(Firm $firm, string $email, CarbonImmutable $startsAt): Appointment
{
    $lead = Lead::factory()->create([
        'firm_id' => $firm->id,
        'stage' => 'reuniao_marcada',
        'email' => $email,
        'report_token' => Str::random(48),
        'report_expires_at' => $startsAt->addDays(30),
        'profile' => ['sector' => 'comercio', 'regime' => 'presumido', 'band' => 'b3', 'b2b' => 50, 'incentivos' => false, 'exporta' => false],
    ]);
    $specialist = Specialist::withoutGlobalScopes()->firstOrCreate(
        ['firm_id' => $firm->id],
        ['user_id' => User::factory()->create(['firm_id' => $firm->id, 'name' => 'Mariana', 'last_name' => 'Toledo'])->id, 'area' => 'Tributário · Reforma', 'focus' => 'geral'],
    );

    return Appointment::withoutGlobalScopes()->create([
        'firm_id' => $firm->id,
        'lead_id' => $lead->id,
        'specialist_id' => $specialist->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addMinutes(30),
    ]);
}

beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-23 09:00', 'America/Sao_Paulo'));
    Mail::fake();
    $this->franco = Firm::factory()->create(['slug' => 'franco']);
    $this->other = Firm::factory()->create(['slug' => 'outra']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

it('reminds only the meetings of the next 24 hours, in every firm, once', function () {
    $now = CarbonImmutable::now();

    $soon = remindableMeeting($this->franco, 'em-duas-horas@exemplo.com', $now->addHours(2));
    $tomorrow = remindableMeeting($this->other, 'amanha@exemplo.com', $now->addHours(23)->addMinutes(30));
    remindableMeeting($this->franco, 'depois@exemplo.com', $now->addHours(30));
    remindableMeeting($this->franco, 'passou@exemplo.com', $now->subHour());

    $this->artisan('appointments:remind')->assertSuccessful();

    Mail::assertQueuedCount(2);
    Mail::assertQueued(MeetingReminder::class, fn (MeetingReminder $mail) => $mail->hasTo('em-duas-horas@exemplo.com'));
    Mail::assertQueued(MeetingReminder::class, fn (MeetingReminder $mail) => $mail->hasTo('amanha@exemplo.com')
        && str_contains($mail->render(), 'Mariana Toledo'));

    expect($soon->refresh()->reminded_at)->not->toBeNull()
        ->and($tomorrow->refresh()->reminded_at)->not->toBeNull();

    $this->artisan('appointments:remind')->assertSuccessful();

    Mail::assertQueuedCount(2);
});

it('is scheduled every hour', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'appointments:remind'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *');
});
