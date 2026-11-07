<?php

use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaVote;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Review of 2026-09-17 (ARC-10): A merged into B, then C merged into A answered 200 — C's
 * supporters moved onto A, which is hidden and merged, and vanished from the board. A merge of
 * an id that does not exist answered 400 "An idea cannot be merged into itself", which was
 * false. Each refusal now says which rule it is, with an i18n key, and a missing idea is 404.
 */

uses(RefreshDatabase::class);

describe('what a merge refuses', function () {
    it('answers 404 when the duplicate does not exist', function () {
        $target = Idea::factory()->create();

        $this->actingAs(ideaMaintainer())
            ->postJson('/api/ideas/999999/merge', ['targetId' => $target->id])
            ->assertNotFound();
    });

    it('refuses an idea merged into itself with its own key', function () {
        $idea = Idea::factory()->create();

        $this->actingAs(ideaMaintainer())
            ->postJson("/api/ideas/{$idea->id}/merge", ['targetId' => $idea->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.targetId.0', 'ideas.merge.sameIdea');
    });

    it('refuses a target that was itself merged, and moves no supporter', function () {
        $maintainer = ideaMaintainer();
        $first = Idea::factory()->create();
        $canonical = Idea::factory()->create();
        $late = Idea::factory()->create();

        $this->actingAs($maintainer)->postJson("/api/ideas/{$first->id}/merge", ['targetId' => $canonical->id])->assertOk();
        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$late->id}/vote")->assertOk();

        $this->actingAs($maintainer)
            ->postJson("/api/ideas/{$late->id}/merge", ['targetId' => $first->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.targetId.0', 'ideas.merge.targetUnavailable');

        expect(IdeaVote::where('idea_id', $late->id)->count())->toBe(1);
        expect($late->fresh()->merged_into_id)->toBeNull();
    });

    it('refuses a hidden target', function () {
        $hidden = Idea::factory()->hidden()->create();
        $duplicate = Idea::factory()->create();

        $this->actingAs(ideaMaintainer())
            ->postJson("/api/ideas/{$duplicate->id}/merge", ['targetId' => $hidden->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.targetId.0', 'ideas.merge.targetUnavailable');
    });

    it('refuses to merge a duplicate that was already merged', function () {
        $maintainer = ideaMaintainer();
        $duplicate = Idea::factory()->create();
        $first = Idea::factory()->create();
        $second = Idea::factory()->create();

        $this->actingAs($maintainer)->postJson("/api/ideas/{$duplicate->id}/merge", ['targetId' => $first->id])->assertOk();

        $this->actingAs($maintainer)
            ->postJson("/api/ideas/{$duplicate->id}/merge", ['targetId' => $second->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.id.0', 'ideas.merge.alreadyMerged');

        expect($duplicate->fresh()->merged_into_id)->toBe($first->id);
    });
});
