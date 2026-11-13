<?php

use App\Models\User;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Firm\Models\Firm;
use App\Modules\Notification\Notifications\AssistantNoteExpiringNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\FirmSeeder;
use Database\Seeders\LocalUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\DatabaseNotification;

/*
 * A note's review runs out in sight (rnc-3 R11, AM19, A28):
 *
 *   GET /api/assistant/notes            each note's `expires_in_days`, and `expiring_count`
 *   php artisan assistant:notify-expiring   daily at 07:00 São Paulo, per firm
 *
 * A review is served through day 90 (São Paulo calendar days, W52). From day 83 — seven days left —
 * the note counts in "N notas vencem em 7 dias" and its reviewer's bell rings, once per review: a
 * second run rings nobody, and a new review starts the count again.
 */
beforeEach(function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-19 10:00', 'America/Sao_Paulo'));
    $this->seed([FirmSeeder::class, RoleSeeder::class, LocalUsersSeeder::class]);
    $this->franco = Firm::where('slug', 'franco')->firstOrFail();
    $this->mariana = User::where('username', 'mariana.toledo')->firstOrFail();
    $this->carlos = User::where('username', 'carlos.prado')->firstOrFail();
    $this->reinaldo = User::where('username', 'reinaldo')->firstOrFail();
});

afterEach(fn () => CarbonImmutable::setTestNow());

/** A note of Franco's reviewed by Mariana `$days` São Paulo days ago, at 15:00 that day. */
function noteReviewedDaysAgo(int $days, array $attributes = []): AssistantNote
{
    $reviewedAt = CarbonImmutable::now('America/Sao_Paulo')->startOfDay()->subDays($days)->setTime(15, 0)->utc();

    return AssistantNote::factory()->create([
        'firm_id' => Firm::where('slug', 'franco')->value('id'),
        'reviewer_id' => User::where('username', 'mariana.toledo')->value('id'),
        'reviewed_at' => $reviewedAt,
        ...$attributes,
    ]);
}

function expiringCount(User $viewer): int
{
    return test()->actingAs($viewer)->getJson('/api/assistant/notes')->assertOk()->json('expiring_count');
}

function notifyExpiring(): void
{
    test()->artisan('assistant:notify-expiring')->assertSuccessful();
}

describe('the bell at day 83 (A28)', function () {
    it('rings nobody for a note reviewed 82 days ago', function () {
        noteReviewedDaysAgo(82);

        notifyExpiring();

        expect(DatabaseNotification::count())->toBe(0);
    });

    it('rings the reviewer once at day 83, and not again on the next run', function () {
        $note = noteReviewedDaysAgo(83);

        notifyExpiring();
        notifyExpiring();

        $bell = DatabaseNotification::sole();

        expect($bell->notifiable_id)->toBe($this->mariana->id)
            ->and($bell->data)->toBe([
                'kind' => AssistantNoteExpiringNotification::KIND,
                'note_id' => $note->id,
                'question' => $note->question,
                'expires_on' => '2026-11-26',
            ]);
    });

    it('rings the next day too when day 83 had no run, still once', function () {
        noteReviewedDaysAgo(86);

        notifyExpiring();
        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());
        notifyExpiring();

        expect(DatabaseNotification::count())->toBe(1);
    });

    it('rings nobody for a review already out of date, a draft or a retired note', function () {
        noteReviewedDaysAgo(91);
        noteReviewedDaysAgo(85, ['status' => 'rascunho']);
        noteReviewedDaysAgo(85, ['status' => 'aposentada']);

        notifyExpiring();

        expect(DatabaseNotification::count())->toBe(0);
    });

    it('rings again once a new review reaches its own day 83', function () {
        $note = noteReviewedDaysAgo(83);
        notifyExpiring();

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDays(2));
        $this->actingAs($this->carlos)->postJson("/api/assistant/notes/{$note->id}/review")->assertOk();
        notifyExpiring();
        expect(DatabaseNotification::count())->toBe(1);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDays(83));
        notifyExpiring();

        expect(DatabaseNotification::count())->toBe(2)
            ->and(DatabaseNotification::query()->latest('created_at')->first()->notifiable_id)->toBe($this->carlos->id);
    });

    it('rings no inactive reviewer and no note without one', function () {
        noteReviewedDaysAgo(84);
        noteReviewedDaysAgo(84, ['reviewer_id' => null]);
        $this->mariana->update(['status' => 'inactive']);

        notifyExpiring();

        expect(DatabaseNotification::count())->toBe(0);
    });

    it("walks every firm: firm B's note rings firm B's reviewer", function () {
        $firmB = Firm::factory()->create(['slug' => 'firma-b']);
        $theirs = assistantPerson('equipe', specialist: true, firm: $firmB);
        noteReviewedDaysAgo(83);
        noteReviewedDaysAgo(84, ['firm_id' => $firmB->id, 'reviewer_id' => $theirs->id]);

        notifyExpiring();

        expect(DatabaseNotification::pluck('notifiable_id')->sort()->values()->all())
            ->toBe(collect([$this->mariana->id, $theirs->id])->sort()->values()->all());
    });

    it('is scheduled every day at 07:00 São Paulo', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'assistant:notify-expiring'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 7 * * *')
            ->and((string) $event->timezone)->toBe('America/Sao_Paulo');
    });
});

describe('the counter "N notas vencem em 7 dias" (A28)', function () {
    it('goes from 0 to 1 the day a note turns 83 days old', function () {
        noteReviewedDaysAgo(82);

        expect(expiringCount($this->reinaldo))->toBe(0);

        CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());

        expect(expiringCount($this->reinaldo))->toBe(1);
    });

    it('counts the reviewed notes 83 to 90 days old, and nothing else', function () {
        foreach ([1, 82, 83, 87, 90, 91] as $days) {
            noteReviewedDaysAgo($days);
        }
        noteReviewedDaysAgo(85, ['status' => 'rascunho']);
        noteReviewedDaysAgo(85, ['firm_id' => Firm::factory()->create(['slug' => 'firma-b'])->id]);

        expect(expiringCount($this->mariana))->toBe(3);
    });

    it('says on each note how many days its review has left', function () {
        $fresh = noteReviewedDaysAgo(1);
        $warned = noteReviewedDaysAgo(83);
        $lastDay = noteReviewedDaysAgo(90);
        $expired = noteReviewedDaysAgo(91);
        $draft = noteReviewedDaysAgo(85, ['status' => 'rascunho']);

        $days = collect($this->actingAs($this->reinaldo)->getJson('/api/assistant/notes')->assertOk()->json('data'))
            ->mapWithKeys(fn (array $row) => [$row['id'] => $row['expires_in_days']]);

        expect($days[$fresh->id])->toBe(89)
            ->and($days[$warned->id])->toBe(7)
            ->and($days[$lastDay->id])->toBe(0)
            ->and($days[$expired->id])->toBeNull()
            ->and($days[$draft->id])->toBeNull();
    });
});
