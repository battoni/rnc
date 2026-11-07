<?php

use App\Modules\Idea\Enums\IdeaStatus;
use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaVote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('PATCH /api/ideas/{id}/status', function () {
    it('sets the status together with the public note explaining it', function () {
        $idea = Idea::factory()->create();

        $response = $this->actingAs(ideaMaintainer())->patchJson("/api/ideas/{$idea->id}/status", [
            'status' => IdeaStatus::NOT_NOW->value,
            'adminNote' => 'I understand the request, but it is a whole extra product to maintain.',
        ]);

        $response->assertOk();
        expect($response->json('data.status'))->toBe(IdeaStatus::NOT_NOW->value);
        expect($response->json('data.adminNote'))->toContain('whole extra product');
    });

    it('stamps deliveredAt when it becomes delivered', function () {
        $idea = Idea::factory()->create();

        $response = $this->actingAs(ideaMaintainer())->patchJson("/api/ideas/{$idea->id}/status", [
            'status' => IdeaStatus::DELIVERED->value,
        ]);

        expect($response->json('data.deliveredAt'))->not->toBeNull();
    });

    it('clears deliveredAt when a delivery is walked back', function () {
        $idea = Idea::factory()->status(IdeaStatus::DELIVERED)->create();

        $response = $this->actingAs(ideaMaintainer())->patchJson("/api/ideas/{$idea->id}/status", [
            'status' => IdeaStatus::IN_PROGRESS->value,
        ]);

        expect($response->json('data.deliveredAt'))->toBeNull();
    });

    it('keeps the original delivery date when a delivered idea is edited again', function () {
        $idea = Idea::factory()->create();
        $maintainer = ideaMaintainer();

        $first = $this->actingAs($maintainer)->patchJson("/api/ideas/{$idea->id}/status", [
            'status' => IdeaStatus::DELIVERED->value,
        ])->json('data.deliveredAt');

        $second = $this->actingAs($maintainer)->patchJson("/api/ideas/{$idea->id}/status", [
            'status' => IdeaStatus::DELIVERED->value,
            'adminNote' => 'Detalhe adicional.',
        ])->json('data.deliveredAt');

        expect($second)->toBe($first);
    });

    it('is closed to a contributor', function () {
        $idea = Idea::factory()->create();

        $this->actingAs(ideaContributor())->patchJson("/api/ideas/{$idea->id}/status", [
            'status' => IdeaStatus::PLANNED->value,
        ])->assertForbidden();
    });
});

describe('PATCH /api/ideas/{id}/hide', function () {
    it('takes the idea off the board for everyone else', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->create();

        $this->actingAs(ideaMaintainer())->patchJson("/api/ideas/{$idea->id}/hide")->assertOk();

        expect($this->actingAs(ideaContributor())->getJson('/api/ideas')->json('data'))->toHaveCount(0);
        $this->actingAs(ideaContributor())->getJson("/api/ideas/{$idea->id}")->assertNotFound();
    });

    it('hides it from its own author too', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->create();

        $this->actingAs(ideaMaintainer())->patchJson("/api/ideas/{$idea->id}/hide");

        $this->actingAs($author)->getJson("/api/ideas/{$idea->id}")->assertNotFound();
    });

    it('keeps it visible to the maintainer, flagged as hidden', function () {
        $idea = Idea::factory()->create();
        $maintainer = ideaMaintainer();

        $this->actingAs($maintainer)->patchJson("/api/ideas/{$idea->id}/hide");

        $board = $this->actingAs($maintainer)->getJson('/api/ideas')->json('data');
        expect($board)->toHaveCount(1);
        expect($board[0]['hidden'])->toBeTrue();
    });

    it('is reversible', function () {
        $idea = Idea::factory()->create();
        $maintainer = ideaMaintainer();

        $this->actingAs($maintainer)->patchJson("/api/ideas/{$idea->id}/hide");
        $response = $this->actingAs($maintainer)->patchJson("/api/ideas/{$idea->id}/hide");

        expect($response->json('data.hidden'))->toBeFalse();
    });
});

describe('POST /api/ideas/{id}/merge', function () {
    it('moves the duplicate supporters onto the canonical idea', function () {
        $duplicate = Idea::factory()->create();
        $target = Idea::factory()->create();

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$duplicate->id}/vote");
        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$duplicate->id}/vote");

        $response = $this->actingAs(ideaMaintainer())
            ->postJson("/api/ideas/{$duplicate->id}/merge", ['targetId' => $target->id]);

        $response->assertOk();
        expect($response->json('data.id'))->toBe($target->id);
        expect($response->json('data.votesCount'))->toBe(2);
    });

    it('counts somebody who supported both only once', function () {
        $duplicate = Idea::factory()->create();
        $target = Idea::factory()->create();
        $bothSupporter = ideaContributor();

        $this->actingAs($bothSupporter)->postJson("/api/ideas/{$duplicate->id}/vote");
        $this->actingAs($bothSupporter)->postJson("/api/ideas/{$target->id}/vote");

        $response = $this->actingAs(ideaMaintainer())
            ->postJson("/api/ideas/{$duplicate->id}/merge", ['targetId' => $target->id]);

        expect($response->json('data.votesCount'))->toBe(1);
        expect(IdeaVote::where('idea_id', $duplicate->id)->count())->toBe(0);
    });

    it('hides the duplicate and points it at the canonical idea', function () {
        $duplicate = Idea::factory()->create();
        $target = Idea::factory()->create();

        $this->actingAs(ideaMaintainer())->postJson("/api/ideas/{$duplicate->id}/merge", ['targetId' => $target->id]);

        $duplicate->refresh();
        expect($duplicate->isHidden())->toBeTrue();
        expect($duplicate->merged_into_id)->toBe($target->id);
    });

    it('refuses to merge an idea into itself', function () {
        $idea = Idea::factory()->create();

        $this->actingAs(ideaMaintainer())
            ->postJson("/api/ideas/{$idea->id}/merge", ['targetId' => $idea->id])
            ->assertUnprocessable();
    });

    it('is closed to a contributor', function () {
        $duplicate = Idea::factory()->create();
        $target = Idea::factory()->create();

        $this->actingAs(ideaContributor())
            ->postJson("/api/ideas/{$duplicate->id}/merge", ['targetId' => $target->id])
            ->assertForbidden();
    });
});
