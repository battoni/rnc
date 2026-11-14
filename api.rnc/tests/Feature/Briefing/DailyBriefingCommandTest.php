<?php

use App\Models\User;
use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\Role;
use App\Modules\Briefing\Mail\DailyBriefingMail;
use App\Modules\Firm\Models\Firm;
use App\Modules\Lead\Models\Lead;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Mail;

/*
 * A24 (rnc-3 R27, AM6): `briefing:send` runs at 07:00 São Paulo — the schedule holds it at
 * `0 7 * * *` in `America/Sao_Paulo` — and on a business day queues the briefing to every active
 * ceo of each firm, built under that firm. On a Saturday, a Sunday or a national holiday (20/11,
 * Consciência Negra) it sends nothing. The mail is queued (`ShouldQueue`, sync locally) and the
 * local mailer stays `log`.
 */
const DAILY_ZONE = 'America/Sao_Paulo';

function dailyCeo(Firm $firm, string $username, UserStatus $status = UserStatus::ACTIVE): User
{
    return User::factory()->create([
        'firm_id' => $firm->id,
        'role_id' => Role::where('slug', 'gestao')->value('id'),
        'username' => $username,
        'name' => ucfirst($username),
        'email' => "{$username}@firma.exemplo",
        'status' => $status,
    ]);
}

function sendDailyBriefing(string $localNow): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($localNow, DAILY_ZONE));
    test()->artisan('briefing:send')->assertSuccessful();
}

beforeEach(function () {
    Mail::fake();
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->reinaldo = User::where('username', 'reinaldo')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

describe('briefing:send (A24)', function () {
    it('is scheduled every day at 07:00 São Paulo, whatever the app’s own zone', function () {
        // The app's zone is São Paulo too, so the schedule is rebuilt under UTC: only an explicit
        // `->timezone('America/Sao_Paulo')` on the event keeps 07:00 in São Paulo.
        app()->instance(Schedule::class, new Schedule('UTC'));
        Facade::clearResolvedInstance(Schedule::class);
        require base_path('routes/console.php');

        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'briefing:send'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 7 * * *')
            ->and((string) $event->timezone)->toBe('America/Sao_Paulo');
    });

    it('queues one briefing per active ceo of each firm on a business day, none to an inactive ceo or the team', function () {
        $sofia = dailyCeo($this->franco, 'sofia');
        dailyCeo($this->franco, 'antigo', UserStatus::INACTIVE);
        $firmB = Firm::factory()->create(['slug' => 'firma-b', 'name' => 'Firma B Contábil']);
        $bruno = dailyCeo($firmB, 'bruno');
        Lead::factory()->create(['firm_id' => $firmB->id, 'stage' => 'lead', 'created_at' => CarbonImmutable::parse('2026-11-12 18:00', DAILY_ZONE)]);

        sendDailyBriefing('2026-11-13 07:00'); // a Friday

        Mail::assertQueuedCount(3);
        Mail::assertQueued(DailyBriefingMail::class, fn (DailyBriefingMail $mail) => $mail->hasTo($this->reinaldo->email) && $mail->locale === 'pt_BR');
        Mail::assertQueued(DailyBriefingMail::class, fn (DailyBriefingMail $mail) => $mail->hasTo($sofia->email));
        Mail::assertQueued(DailyBriefingMail::class, fn (DailyBriefingMail $mail) => $mail->hasTo($bruno->email)
            && $mail->briefing->firmName === 'Firma B Contábil'
            && $mail->briefing->briefing['leads']['last_24_hours'] === 1);
        Mail::assertQueued(DailyBriefingMail::class, fn (DailyBriefingMail $mail) => $mail->hasTo($this->reinaldo->email)
            && $mail->briefing->briefing['leads']['last_24_hours'] === 0);
        Mail::assertNotQueued(DailyBriefingMail::class, fn (DailyBriefingMail $mail) => $mail->hasTo('antigo@firma.exemplo'));
        Mail::assertNotQueued(DailyBriefingMail::class, fn (DailyBriefingMail $mail) => $mail->hasTo(User::where('username', 'patricia')->value('email')));
    });

    it('sends nothing on a Saturday, a Sunday or a national holiday', function (string $localNow) {
        sendDailyBriefing($localNow);

        Mail::assertNothingQueued();
        Mail::assertNothingSent();
    })->with([
        'Saturday 14/11' => ['2026-11-14 07:00'],
        'Sunday 15/11 (also a holiday)' => ['2026-11-15 07:00'],
        'Friday 20/11, Consciência Negra' => ['2026-11-20 07:00'],
    ]);

    it('is a queued mail in pt-BR that greets the ceo, carries the briefing and links to /gestao', function () {
        sendDailyBriefing('2026-11-13 07:00');

        Mail::assertQueued(DailyBriefingMail::class, function (DailyBriefingMail $mail) {
            $mail->assertSeeInHtml('Bom dia, Reinaldo')
                ->assertSeeInHtml('Briefing do dia')
                ->assertSeeInHtml('Novos leads · 7 dias')
                ->assertSeeInHtml('http://franco.localhost:5173/gestao')
                ->assertSeeInText('Bom dia, Reinaldo')
                ->assertSeeInText('http://franco.localhost:5173/gestao');

            return $mail instanceof ShouldQueue && $mail->hasTo($this->reinaldo->email);
        });
    });

    it('keeps the local mailer on log', function () {
        expect(file_get_contents(base_path('.env.example')))->toMatch('/^MAIL_MAILER=log$/m');
    });
});
