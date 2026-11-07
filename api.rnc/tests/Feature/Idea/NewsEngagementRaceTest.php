<?php

use App\Modules\Idea\Models\News;
use App\Modules\Idea\Models\NewsReaction;
use App\Modules\Idea\Models\NewsRead;
use Illuminate\Support\Facades\DB;

/*
 * Review of 2026-09-17 (ARC-12): two tabs opening the same entry, or two thumbs landing the first
 * clap, both see "no row yet" and both insert — the loser hits the unique index. The race is
 * simulated here by writing the winner's row in the instant between the lookup and the insert. The request must read the winner back, not answer 500.
 */

/**
 * Writes the winner's row right after the loser's lookup came back empty — the instant between
 * "no row yet" and the insert. It is done from a query listener, not from the model's `creating`
 * event, because `createOrFirst` inserts inside a savepoint and a row written from inside it
 * would be rolled back with the loser's attempt, which a real concurrent request never is.
 */
function newsRaceWinnerInsertsFirst(string $table, array $row): void
{
    $alreadyRaced = false;

    DB::listen(function ($query) use (&$alreadyRaced, $table, $row): void {
        $isLookup = str_starts_with($query->sql, "select * from \"{$table}\"");

        if ($alreadyRaced || ! $isLookup) {
            return;
        }

        $alreadyRaced = true;

        DB::table($table)->insert($row + ['created_at' => now(), 'updated_at' => now()]);
    });
}

describe('a first read or a first clap that loses the race', function () {
    it('keeps the read the other tab wrote', function () {
        $reader = ideaContributor();
        $entry = News::factory()->create();
        $winnerReadAt = now()->subMinute()->startOfSecond();

        newsRaceWinnerInsertsFirst('news_reads', [
            'news_id' => $entry->id,
            'user_id' => $reader->id,
            'read_at' => $winnerReadAt,
        ]);

        $this->actingAs($reader)->postJson("/api/news/{$entry->id}/read")->assertOk();

        expect(NewsRead::where('news_id', $entry->id)->count())->toBe(1)
            ->and(NewsRead::where('news_id', $entry->id)->first()->read_at->timestamp)->toBe($winnerReadAt->timestamp);
    });

    it('counts the clap on top of the row the other click wrote', function () {
        $reader = ideaContributor();
        $entry = News::factory()->create();

        newsRaceWinnerInsertsFirst('news_reactions', [
            'news_id' => $entry->id,
            'user_id' => $reader->id,
            'type' => 'clap',
            'count' => 1,
        ]);

        $this->actingAs($reader)->postJson("/api/news/{$entry->id}/reactions", ['type' => 'clap'])->assertOk();

        expect(NewsReaction::where('news_id', $entry->id)->sole()->count)->toBe(2);
    });
});
