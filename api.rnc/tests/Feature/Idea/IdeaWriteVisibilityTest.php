<?php

use App\Modules\Idea\Enums\IdeaType;
use App\Modules\Idea\Models\Idea;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Review of 2026-09-17 (ARC-06): GET of a hidden idea answered 404, but PATCH/DELETE of the same
 * idea answered 403 — confirming the hidden row exists — and a missing id answered 403 as well.
 * The author of a hidden idea could still rewrite and delete it, and read it back in the
 * response. A write sees exactly what a read sees: hidden or missing is "not found".
 */

uses(RefreshDatabase::class);

function ideaWritePayload(): array
{
    return [
        'title' => 'Título reescrito',
        'description' => 'Descrição reescrita.',
        'type' => IdeaType::IMPROVEMENT->value,
    ];
}

describe('writing an idea the reader cannot see', function () {
    it('answers 404 to PATCH and DELETE of an id that does not exist', function () {
        $contributor = ideaContributor();

        $this->actingAs($contributor)->patchJson('/api/ideas/999999', ideaWritePayload())->assertNotFound();
        $this->actingAs($contributor)->deleteJson('/api/ideas/999999')->assertNotFound();
    });

    it('answers 404 to another contributor writing a hidden idea', function () {
        $idea = Idea::factory()->hidden()->create();
        $contributor = ideaContributor();

        $this->actingAs($contributor)->patchJson("/api/ideas/{$idea->id}", ideaWritePayload())->assertNotFound();
        $this->actingAs($contributor)->deleteJson("/api/ideas/{$idea->id}")->assertNotFound();
    });

    it('answers 404 to the author of a hidden idea, and leaves it untouched', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->hidden()->create(['title' => 'Original']);

        $this->actingAs($author)->patchJson("/api/ideas/{$idea->id}", ideaWritePayload())->assertNotFound();
        $this->actingAs($author)->deleteJson("/api/ideas/{$idea->id}")->assertNotFound();

        expect(Idea::find($idea->id)?->title)->toBe('Original');
    });

    it('still answers 403 on a visible idea the person may not edit', function () {
        $idea = Idea::factory()->create();

        $this->actingAs(ideaContributor())->patchJson("/api/ideas/{$idea->id}", ideaWritePayload())->assertForbidden();
        $this->actingAs(ideaContributor())->deleteJson("/api/ideas/{$idea->id}")->assertForbidden();
    });

    it('lets a moderator write a hidden idea', function () {
        $idea = Idea::factory()->hidden()->create();

        $this->actingAs(ideaMaintainer())->patchJson("/api/ideas/{$idea->id}", ideaWritePayload())->assertOk();
        $this->actingAs(ideaMaintainer())->deleteJson("/api/ideas/{$idea->id}")->assertOk();
    });
});
