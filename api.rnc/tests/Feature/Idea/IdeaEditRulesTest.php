<?php

use App\Modules\Idea\Enums\IdeaStatus;
use App\Modules\Idea\Enums\IdeaType;
use App\Modules\Idea\Models\Idea;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** The payload an update needs; the fields are all required on PATCH. */
function ideaEditPayload(string $title = 'Título reescrito'): array
{
    return [
        'title' => $title,
        'description' => 'Descrição reescrita.',
        'type' => IdeaType::IMPROVEMENT->value,
    ];
}

describe('PATCH /api/ideas/{id}', function () {
    it('lets the author rewrite an untouched idea', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->create();

        $response = $this->actingAs($author)->patchJson("/api/ideas/{$idea->id}", ideaEditPayload());

        $response->assertOk();
        expect($response->json('data.title'))->toBe('Título reescrito');
    });

    it('stops the author once somebody has supported it', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->create();

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/vote");

        $this->actingAs($author)->patchJson("/api/ideas/{$idea->id}", ideaEditPayload())->assertForbidden();
    });

    it('stops the author once it has been triaged', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->status(IdeaStatus::PLANNED)->create();

        $this->actingAs($author)->patchJson("/api/ideas/{$idea->id}", ideaEditPayload())->assertForbidden();
    });

    it('never lets one contributor rewrite an idea written by another', function () {
        $idea = Idea::factory()->create();

        $this->actingAs(ideaContributor())->patchJson("/api/ideas/{$idea->id}", ideaEditPayload())->assertForbidden();
    });

    it('lets the maintainer rewrite anything, triaged or supported', function () {
        $idea = Idea::factory()->status(IdeaStatus::IN_PROGRESS)->supported(4)->create();

        $response = $this->actingAs(ideaMaintainer())->patchJson("/api/ideas/{$idea->id}", ideaEditPayload());

        $response->assertOk();
        expect($response->json('data.title'))->toBe('Título reescrito');
    });

    it('tells the client the same rule it enforces, through canEdit', function () {
        $author = ideaContributor();
        $own = Idea::factory()->for($author, 'user')->create();
        $triaged = Idea::factory()->for($author, 'user')->status(IdeaStatus::PLANNED)->create();

        $board = $this->actingAs($author)->getJson('/api/ideas?sort=recent')->json('data');
        $byId = collect($board)->keyBy('id');

        expect($byId[$own->id]['canEdit'])->toBeTrue();
        expect($byId[$triaged->id]['canEdit'])->toBeFalse();
    });
});

describe('DELETE /api/ideas/{id}', function () {
    it('lets the author withdraw an untouched idea', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->create();

        $this->actingAs($author)->deleteJson("/api/ideas/{$idea->id}")->assertOk();
        expect(Idea::find($idea->id))->toBeNull();
    });

    it('refuses once it has supporters', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->create();

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/vote");

        $this->actingAs($author)->deleteJson("/api/ideas/{$idea->id}")->assertForbidden();
        expect(Idea::find($idea->id))->not->toBeNull();
    });
});
