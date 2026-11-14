<?php

use App\Models\User;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Auth\Models\Role;
use App\Modules\Briefing\Mail\DailyBriefingMail;
use App\Modules\Firm\Helpers\FirmModules;
use App\Modules\Firm\Models\Firm;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;

/*
 * A18 (rnc-4 R17): scheduled work skips a firm whose module is off. `briefing:send` mails nobody
 * of a firm without `briefing`; `assistant:notify-expiring` rings nobody of a firm without
 * `assistant` — while Franco, which has both, is served as before.
 */
function commandsFirm(array $modules): Firm
{
    return Firm::factory()->create(['slug' => 'alvorada', 'name' => 'Alvorada Contabilidade', 'modules' => $modules]);
}

function commandsPerson(Firm $firm, string $role, string $username, bool $specialist = false): User
{
    return User::factory()->create([
        'firm_id' => $firm->id,
        'role_id' => Role::where('slug', $role)->value('id'),
        'username' => $username,
        'email' => "{$username}@alvorada.exemplo",
        'is_specialist' => $specialist,
    ]);
}

/** A note reviewed 83 São Paulo days ago — its reviewer's bell is due today. */
function commandsExpiringNote(Firm $firm, User $reviewer): AssistantNote
{
    return AssistantNote::factory()->create([
        'firm_id' => $firm->id,
        'reviewer_id' => $reviewer->id,
        'reviewed_at' => CarbonImmutable::now('America/Sao_Paulo')->startOfDay()->subDays(83)->setTime(15, 0)->utc(),
    ]);
}

beforeEach(function () {
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('briefing:send (A18)', function () {
    it('mails nobody of a firm without briefing, and Franco’s manager as before', function () {
        Mail::fake();
        $firm = commandsFirm(array_values(array_diff(FirmModules::ALL, ['briefing'])));
        $helena = commandsPerson($firm, 'gestao', 'helena');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-13 07:00', 'America/Sao_Paulo')); // a Friday

        $this->artisan('briefing:send')->assertSuccessful();

        Mail::assertQueued(DailyBriefingMail::class, fn (DailyBriefingMail $mail) => $mail->hasTo(User::where('username', 'reinaldo')->value('email')));
        Mail::assertNotQueued(DailyBriefingMail::class, fn (DailyBriefingMail $mail) => $mail->hasTo($helena->email));
    });

    it('mails the firm’s manager once briefing is on', function () {
        Mail::fake();
        $firm = commandsFirm(['briefing']);
        $helena = commandsPerson($firm, 'gestao', 'helena');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-13 07:00', 'America/Sao_Paulo'));

        $this->artisan('briefing:send')->assertSuccessful();

        Mail::assertQueued(DailyBriefingMail::class, fn (DailyBriefingMail $mail) => $mail->hasTo($helena->email));
    });
});

describe('assistant:notify-expiring (A18)', function () {
    beforeEach(fn () => CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo')));

    it('rings nobody of a firm without assistant, and Franco’s reviewer as before', function () {
        $firm = commandsFirm(array_values(array_diff(FirmModules::ALL, ['assistant'])));
        $bruno = commandsPerson($firm, 'equipe', 'bruno', specialist: true);
        commandsExpiringNote($firm, $bruno);
        $mariana = User::where('username', 'mariana.toledo')->firstOrFail();
        commandsExpiringNote($this->franco, $mariana);

        $this->artisan('assistant:notify-expiring')->assertSuccessful();

        expect(DatabaseNotification::pluck('notifiable_id')->all())->toBe([$mariana->id]);
    });

    it('rings the firm’s reviewer once assistant is on', function () {
        $firm = commandsFirm(['assistant']);
        $bruno = commandsPerson($firm, 'equipe', 'bruno', specialist: true);
        commandsExpiringNote($firm, $bruno);

        $this->artisan('assistant:notify-expiring')->assertSuccessful();

        expect(DatabaseNotification::pluck('notifiable_id')->all())->toBe([$bruno->id]);
    });
});
