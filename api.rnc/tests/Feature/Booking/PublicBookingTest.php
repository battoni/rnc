<?php

use App\Models\User;
use App\Modules\Auth\Models\Role;
use App\Modules\Booking\Mail\BookingConfirmed;
use App\Modules\Booking\Models\Appointment;
use App\Modules\Booking\Models\Specialist;
use App\Modules\Booking\Repositories\AppointmentRepository;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use App\Modules\Lead\Models\LeadEvent;
use App\Modules\Notification\Notifications\MeetingBookedNotification;
use App\Modules\Notification\Notifications\NewLeadNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SpecialistSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
 * Booking (rnc-1 F4, R15–R17, A9/A10; plan AM4, AM5, AM9, AM13):
 *
 *   GET  /api/public/diagnostics/{token}/availability   the specialists' free 30-min slots, next 10 working days
 *   POST /api/public/diagnostics/{token}/booking        books one → `reuniao_marcada`, the e-mail, the bell
 *
 * The clock is Thursday 19/11/2026 10:00 in São Paulo. Today is offered from noon on — a slot needs
 * 2 hours of notice (W57) — tomorrow is Consciência Negra (20/11) and then a weekend, so the next
 * day is Monday 23/11, and the tenth working day, today counted, is 03/12.
 */
const BOOKING_ZONE = 'America/Sao_Paulo';

const BOOKING_PROFILE = ['sector' => 'saude', 'regime' => 'simples', 'band' => 'b2', 'b2b' => 30, 'incentivos' => false, 'exporta' => false];

function bookingNow(): CarbonImmutable
{
    return CarbonImmutable::parse('2026-11-19 10:00', BOOKING_ZONE);
}

/** A lead that has seen its result (stage `lead`), in Franco, with a complete profile. */
function bookableLead(array $overrides = []): Lead
{
    return Lead::factory()->create(array_merge([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'stage' => 'lead',
        'last_step' => 'resultado',
        'company' => 'Clínica Horizonte',
        'contact_name' => 'Ana Souza',
        'email' => 'ana@horizonte.com.br',
        'profile' => BOOKING_PROFILE,
    ], $overrides));
}

function specialistNamed(string $username): Specialist
{
    return Specialist::withoutGlobalScopes()->whereHas('user', fn ($query) => $query->where('username', $username))->sole();
}

/** @return array<string, mixed> */
function specialistAnswer(array $availability, int $specialistId): array
{
    return collect($availability['specialists'])->firstWhere('id', $specialistId);
}

function slotAt(string $local): string
{
    return CarbonImmutable::parse($local, BOOKING_ZONE)->toIso8601String();
}

beforeEach(function () {
    CarbonImmutable::setTestNow(bookingNow());
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class, SpecialistSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('availability (R15, A9)', function () {
    it('offers each specialist the next 10 working days, today counted, in 30-minute slots of their hours', function () {
        $lead = bookableLead();

        $availability = $this->getJson("/api/public/diagnostics/{$lead->token}/availability")->assertOk()->json('data');

        expect($availability['slot_minutes'])->toBe(30)
            ->and($availability['specialists'])->toHaveCount(4);

        $mariana = specialistAnswer($availability, specialistNamed('mariana.toledo')->id);

        expect($mariana['name'])->toBe('Mariana Toledo')
            ->and($mariana['area'])->toBe('Tributário · Reforma')
            ->and(array_column($mariana['days'], 'date'))->toBe([
                '2026-11-19', '2026-11-23', '2026-11-24', '2026-11-25', '2026-11-26',
                '2026-11-27', '2026-11-30', '2026-12-01', '2026-12-02', '2026-12-03',
            ]);

        $monday = $mariana['days'][1];
        $friday = $mariana['days'][5];

        expect($monday['slots'])->toHaveCount(20)
            ->and($monday['slots'][0])->toBe('2026-11-23T08:00:00-03:00')
            ->and($monday['slots'][1])->toBe('2026-11-23T08:30:00-03:00')
            ->and(end($monday['slots']))->toBe('2026-11-23T17:30:00-03:00')
            ->and($friday['slots'])->toHaveCount(18)
            ->and(end($friday['slots']))->toBe('2026-11-27T16:30:00-03:00');
    });

    it('offers today only the slots at least 2 hours away', function (string $now, ?string $first, int $count) {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($now, BOOKING_ZONE));

        $availability = $this->getJson('/api/public/diagnostics/'.bookableLead()->token.'/availability')->assertOk()->json('data');
        $today = specialistAnswer($availability, specialistNamed('mariana.toledo')->id)['days'][0];

        expect($today['date'])->toBe('2026-11-19')
            ->and($today['slots'][0] ?? null)->toBe($first)
            ->and($today['slots'])->toHaveCount($count);
    })->with([
        'at 10:00 the first is 12:00' => ['2026-11-19 10:00', '2026-11-19T12:00:00-03:00', 12],
        'at 10:01 the first is 12:30' => ['2026-11-19 10:01', '2026-11-19T12:30:00-03:00', 11],
        'at 06:00 the day is whole' => ['2026-11-19 06:00', '2026-11-19T08:00:00-03:00', 20],
        'at 16:00 nothing is left' => ['2026-11-19 16:00', null, 0],
    ]);

    it('has no slot on a holiday or a weekend', function () {
        $lead = bookableLead();

        $availability = $this->getJson("/api/public/diagnostics/{$lead->token}/availability")->assertOk()->json('data');

        $slotDays = collect($availability['specialists'])
            ->flatMap(fn (array $specialist) => Arr::flatten(array_column($specialist['days'], 'slots')))
            ->map(fn (string $slot) => CarbonImmutable::parse($slot)->setTimezone(BOOKING_ZONE))
            ->unique(fn (CarbonImmutable $slot) => $slot->toDateString());

        expect($slotDays->filter(fn (CarbonImmutable $slot) => $slot->isWeekend()))->toBeEmpty()
            ->and($slotDays->map(fn (CarbonImmutable $slot) => $slot->toDateString())->all())->not->toContain('2026-11-20');
    });

    it('marks the specialist indicated for the profile (the demo suggestedSpecialist)', function (array $profile, string $username) {
        $lead = bookableLead(['profile' => [...BOOKING_PROFILE, ...$profile]]);

        $availability = $this->getJson("/api/public/diagnostics/{$lead->token}/availability")->assertOk()->json('data');
        $suggested = specialistNamed($username)->id;

        expect($availability['suggested_specialist_id'])->toBe($suggested)
            ->and(collect($availability['specialists'])->where('suggested', true)->pluck('id')->all())->toBe([$suggested]);
    })->with([
        'Simples' => [['regime' => 'simples', 'sector' => 'industria'], 'juliana.reis'],
        'indústria' => [['regime' => 'presumido', 'sector' => 'industria'], 'carlos.prado'],
        'Real' => [['regime' => 'real', 'sector' => 'servicos'], 'rafael.antunes'],
        'any other' => [['regime' => 'presumido', 'sector' => 'servicos'], 'mariana.toledo'],
    ]);

    it('drops a booked slot from the availability', function () {
        $carlos = specialistNamed('carlos.prado');

        $this->postJson('/api/public/diagnostics/'.bookableLead()->token.'/booking', [
            'specialist_id' => $carlos->id,
            'starts_at' => slotAt('2026-11-23 09:00'),
        ])->assertCreated();

        $availability = $this->getJson('/api/public/diagnostics/'.bookableLead(['email' => 'outro@exemplo.com'])->token.'/availability')->json('data');
        $monday = specialistAnswer($availability, $carlos->id)['days'][1]['slots'];
        $marianaMonday = specialistAnswer($availability, specialistNamed('mariana.toledo')->id)['days'][1]['slots'];

        expect($monday)->toHaveCount(19)
            ->not->toContain('2026-11-23T09:00:00-03:00')
            ->and($marianaMonday)->toContain('2026-11-23T09:00:00-03:00');
    });

    it('answers 404 for an unknown token', function () {
        $this->getJson('/api/public/diagnostics/'.str_repeat('x', 48).'/availability')->assertNotFound();
    });
});

describe('booking (R16, AM5, AM13)', function () {
    it('books the slot, stores it in UTC and moves the lead to reuniao_marcada with a report token', function () {
        $lead = bookableLead();
        $juliana = specialistNamed('juliana.reis');

        $response = $this->postJson("/api/public/diagnostics/{$lead->token}/booking", [
            'specialist_id' => $juliana->id,
            'starts_at' => slotAt('2026-11-24 14:30'),
        ])->assertCreated();

        $lead->refresh();
        $appointment = Appointment::withoutGlobalScopes()->sole();

        expect($appointment->firm_id)->toBe($this->franco->id)
            ->and($appointment->lead_id)->toBe($lead->id)
            ->and($appointment->specialist_id)->toBe($juliana->id)
            ->and($appointment->getRawOriginal('starts_at'))->toBe('2026-11-24 17:30:00')
            ->and($appointment->getRawOriginal('ends_at'))->toBe('2026-11-24 18:00:00')
            ->and($lead->stage)->toBe('reuniao_marcada')
            ->and(strlen((string) $lead->report_token))->toBe(48)
            ->and($lead->report_expires_at->isSameDay(bookingNow()->addDays(30)))->toBeTrue();

        expect($response->json('data'))->toMatchArray([
            'report_token' => $lead->report_token,
            'meeting' => [
                'specialist' => 'Juliana Reis',
                'area' => 'Contábil · Simples Nacional',
                'starts_at' => '2026-11-24T14:30:00-03:00',
                'ends_at' => '2026-11-24T15:00:00-03:00',
            ],
        ]);

        expect(json_encode($response->json()))->not->toContain('horizonte.com.br')->not->toContain('Ana Souza');
        expect(LeadEvent::query()->where('lead_id', $lead->id)->pluck('type')->all())->toContain('reuniao.marcada');
    });

    it('refuses the same slot to a second visitor: "esse horário acabou de ser reservado" (409)', function () {
        $carlos = specialistNamed('carlos.prado');
        $slot = ['specialist_id' => $carlos->id, 'starts_at' => slotAt('2026-11-23 10:00')];

        $this->postJson('/api/public/diagnostics/'.bookableLead()->token.'/booking', $slot)->assertCreated();

        $second = bookableLead(['email' => 'segundo@exemplo.com']);

        $this->postJson("/api/public/diagnostics/{$second->token}/booking", $slot)
            ->assertStatus(409)
            ->assertJsonPath('code', 'slot_taken');

        expect(Appointment::withoutGlobalScopes()->count())->toBe(1)
            ->and($second->refresh()->stage)->toBe('lead');
    });

    /*
     * The pre-check above answers the slow visitor; the unique (specialist, starts_at) index answers
     * the one whose check ran while the other's insert was in flight. The double lets the check
     * miss a row that exists, so the insert is what refuses.
     */
    it('answers 409 slot_taken when the insert meets the unique index the pre-check missed', function (bool $postgres) {
        $carlos = specialistNamed('carlos.prado');
        $slot = ['specialist_id' => $carlos->id, 'starts_at' => slotAt('2026-11-23 10:00')];

        // Bound before the first request: the router keeps the controller (and its service) it built.
        $this->app->instance(AppointmentRepository::class, new class($postgres) extends AppointmentRepository
        {
            public function __construct(private bool $postgres) {}

            public function isAppointmentSlotTaken(Specialist $specialist, CarbonImmutable $startsAt): bool
            {
                return false;
            }

            public function createAppointment(array $attributes): Appointment
            {
                $taken = Appointment::query()->where('specialist_id', $attributes['specialist_id'])->exists();

                return $this->postgres && $taken ? throw postgresUniqueViolation('appointments') : parent::createAppointment($attributes);
            }
        });

        $this->postJson('/api/public/diagnostics/'.bookableLead()->token.'/booking', $slot)->assertCreated();

        $second = bookableLead(['email' => 'segundo@exemplo.com']);

        $this->postJson("/api/public/diagnostics/{$second->token}/booking", $slot)
            ->assertStatus(409)
            ->assertJsonPath('code', 'slot_taken');

        expect(Appointment::withoutGlobalScopes()->count())->toBe(1)
            ->and($second->refresh()->stage)->toBe('lead');
    })->with([
        'the real index (SQLite says 23000)' => [false],
        'PostgreSQL says 23505' => [true],
    ]);

    it('refuses a slot the agenda does not offer (422 slot_unavailable)', function (string $local) {
        $this->postJson('/api/public/diagnostics/'.bookableLead()->token.'/booking', [
            'specialist_id' => specialistNamed('mariana.toledo')->id,
            'starts_at' => slotAt($local),
        ])->assertUnprocessable()->assertJsonPath('code', 'slot_unavailable');

        expect(Appointment::withoutGlobalScopes()->count())->toBe(0);
    })->with([
        'a holiday' => ['2026-11-20 10:00'],
        'a Saturday' => ['2026-11-28 10:00'],
        'before the hours' => ['2026-11-23 07:30'],
        'the Friday close' => ['2026-11-27 17:00'],
        'off the half hour' => ['2026-11-23 10:15'],
        'today, under 2 hours away' => ['2026-11-19 11:30'],
        'today, already past' => ['2026-11-19 09:00'],
        'past the tenth working day' => ['2026-12-04 10:00'],
    ]);

    it('refuses a specialist of another firm as unknown', function () {
        $other = Firm::factory()->create(['slug' => 'outra']);
        $stranger = Specialist::withoutGlobalScopes()->create(['firm_id' => $other->id, 'user_id' => User::factory()->create(['firm_id' => $other->id])->id, 'area' => null, 'focus' => null]);

        $this->postJson('/api/public/diagnostics/'.bookableLead()->token.'/booking', [
            'specialist_id' => $stranger->id,
            'starts_at' => slotAt('2026-11-23 10:00'),
        ])->assertUnprocessable()->assertJsonPath('code', 'slot_unavailable');
    });

    it('asks for the e-mail when the lead has none (422 email_required) and keeps the one given', function () {
        $lead = bookableLead(['email' => null]);
        $slot = ['specialist_id' => specialistNamed('mariana.toledo')->id, 'starts_at' => slotAt('2026-11-25 11:00')];

        $this->postJson("/api/public/diagnostics/{$lead->token}/booking", $slot)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'email_required');

        $this->postJson("/api/public/diagnostics/{$lead->token}/booking", [...$slot, 'email' => 'nao-e-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson("/api/public/diagnostics/{$lead->token}/booking", [...$slot, 'email' => ' Ana@Horizonte.COM.br '])->assertCreated();

        expect($lead->refresh()->email)->toBe('ana@horizonte.com.br');
    });

    it('books a lead only once (409 already_booked) and then locks the profile (409 lead_locked)', function () {
        $lead = bookableLead();
        $mariana = specialistNamed('mariana.toledo')->id;

        $this->postJson("/api/public/diagnostics/{$lead->token}/booking", ['specialist_id' => $mariana, 'starts_at' => slotAt('2026-11-23 08:00')])->assertCreated();

        $this->postJson("/api/public/diagnostics/{$lead->token}/booking", ['specialist_id' => $mariana, 'starts_at' => slotAt('2026-11-23 08:30')])
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_booked');

        $this->patchJson("/api/public/diagnostics/{$lead->token}", ['profile' => ['regime' => 'real']])
            ->assertStatus(409)
            ->assertJsonPath('code', 'lead_locked');
    });

    it('validates the specialist and the time', function () {
        $this->postJson('/api/public/diagnostics/'.bookableLead()->token.'/booking', ['specialist_id' => 'x', 'starts_at' => 'amanhã'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['specialist_id', 'starts_at']);
    });

    /* A time without its offset would be read as the server's guess of a zone: refused as a format error, not a missing slot. */
    it('wants the start as ISO-8601 with its offset, as the availability gave it', function (string $startsAt, bool $accepted) {
        $response = $this->postJson('/api/public/diagnostics/'.bookableLead()->token.'/booking', [
            'specialist_id' => specialistNamed('carlos.prado')->id,
            'starts_at' => $startsAt,
        ]);

        $accepted ? $response->assertCreated() : $response->assertUnprocessable()->assertJsonValidationErrors(['starts_at']);
    })->with([
        'with the São Paulo offset' => ['2026-11-23T10:00:00-03:00', true],
        'in UTC with Z' => ['2026-11-23T13:00:00Z', true],
        'in UTC with +00:00' => ['2026-11-23T13:00:00+00:00', true],
        'without an offset' => ['2026-11-23T10:00:00', false],
        'a date and time with a space' => ['2026-11-23 10:00', false],
        'a date only' => ['2026-11-23', false],
        'an impossible date with an offset' => ['2026-02-30T10:00:00-03:00', false],
    ]);

    it('answers 404 for an unknown token', function () {
        $this->postJson('/api/public/diagnostics/'.str_repeat('x', 48).'/booking', [
            'specialist_id' => specialistNamed('mariana.toledo')->id,
            'starts_at' => slotAt('2026-11-23 10:00'),
        ])->assertNotFound();
    });
});

describe('what a booking sends (R17, A10)', function () {
    it('queues the branded confirmation to the lead with the report link', function () {
        Mail::fake();
        $lead = bookableLead();

        $this->postJson("/api/public/diagnostics/{$lead->token}/booking", [
            'specialist_id' => specialistNamed('juliana.reis')->id,
            'starts_at' => slotAt('2026-11-24 14:30'),
        ])->assertCreated();

        $reportUrl = config('rnc.frontend_url').'/relatorio/'.$lead->refresh()->report_token;

        Mail::assertQueued(BookingConfirmed::class, function (BookingConfirmed $mail) use ($reportUrl) {
            $html = $mail->render();

            return $mail->hasTo('ana@horizonte.com.br')
                && $mail->meeting->reportUrl === $reportUrl
                && str_contains($html, $reportUrl)
                && str_contains($html, 'Juliana Reis')
                && str_contains($html, '24/11')
                && str_contains($html, '14:30')
                && str_contains($html, 'Franco Contabilidade');
        });
        Mail::assertQueuedCount(1);
    });

    it('notifies every equipe and ceo member of the firm, and nobody else', function () {
        Notification::fake();
        $other = Firm::factory()->create(['slug' => 'outra']);
        $stranger = User::factory()->create(['firm_id' => $other->id, 'role_id' => Role::where('slug', 'equipe')->value('id')]);
        $operator = User::factory()->create(['firm_id' => $this->franco->id, 'role_id' => Role::where('slug', 'operador')->value('id')]);

        $lead = bookableLead();

        $this->postJson("/api/public/diagnostics/{$lead->token}/booking", [
            'specialist_id' => specialistNamed('carlos.prado')->id,
            'starts_at' => slotAt('2026-11-26 16:00'),
        ])->assertCreated();

        $staff = User::whereIn('username', ['reinaldo', 'patricia', 'mariana.toledo', 'carlos.prado', 'juliana.reis', 'rafael.antunes'])->get();

        Notification::assertSentTo($staff, MeetingBookedNotification::class, function (MeetingBookedNotification $notification, array $channels, User $notifiable) use ($lead) {
            $data = $notification->toArray($notifiable);

            return $channels === ['database']
                && $data['kind'] === 'meeting_booked'
                && $data['lead_id'] === $lead->id
                && $data['company'] === 'Clínica Horizonte'
                && $data['specialist'] === 'Carlos Prado'
                && $data['starts_at'] === '2026-11-26T16:00:00-03:00';
        });
        Notification::assertNotSentTo([$stranger, $operator], MeetingBookedNotification::class);
    });

    it('notifies the team once when a lead first leaves a contact (NewLead)', function () {
        Notification::fake();

        $token = $this->postJson('/api/public/diagnostics', ['visitor' => (string) Str::uuid(), 'path' => 'quiz', 'source' => 'direto'])->assertCreated()->json('data.token');
        $contact = ['contact' => ['company' => 'Padaria Sol', 'name' => null, 'phone' => '(11) 97777-6666', 'email' => null, 'consent' => false], 'step' => 'perfil'];

        $this->patchJson("/api/public/diagnostics/{$token}", $contact)->assertOk();
        $this->patchJson("/api/public/diagnostics/{$token}", $contact)->assertOk();

        $reinaldo = User::where('username', 'reinaldo')->firstOrFail();

        Notification::assertSentToTimes($reinaldo, NewLeadNotification::class, 1);
        Notification::assertSentTo($reinaldo, NewLeadNotification::class, fn (NewLeadNotification $notification) => $notification->toArray($reinaldo)['kind'] === 'new_lead'
            && $notification->toArray($reinaldo)['company'] === 'Padaria Sol');
    });
});
