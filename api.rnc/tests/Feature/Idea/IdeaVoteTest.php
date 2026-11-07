<?php

use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaVote;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('POST /api/ideas/{id}/vote', function () {
    it('supports an idea and returns the whole idea, not just a count', function () {
        $user = ideaContributor();
        $idea = Idea::factory()->create();

        $response = $this->actingAs($user)->postJson("/api/ideas/{$idea->id}/vote");

        $response->assertOk();
        expect($response->json('data.votesCount'))->toBe(1);
        expect($response->json('data.voted'))->toBeTrue();
        expect($response->json('data.title'))->toBe($idea->title);
    });

    it('counts a second click from the same person once', function () {
        $user = ideaContributor();
        $idea = Idea::factory()->create();

        $this->actingAs($user)->postJson("/api/ideas/{$idea->id}/vote")->assertOk();
        $response = $this->actingAs($user)->postJson("/api/ideas/{$idea->id}/vote");

        $response->assertOk();
        expect($response->json('data.votesCount'))->toBe(1);
        expect(IdeaVote::where('idea_id', $idea->id)->count())->toBe(1);
    });

    it('lets the author support their own idea', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->create();

        $response = $this->actingAs($author)->postJson("/api/ideas/{$idea->id}/vote");

        expect($response->json('data.votesCount'))->toBe(1);
    });

    it('adds up across people', function () {
        $idea = Idea::factory()->create();

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/vote");
        $response = $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/vote");

        expect($response->json('data.votesCount'))->toBe(2);
    });

    it('refuses to support a hidden idea', function () {
        $idea = Idea::factory()->hidden()->create();

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$idea->id}/vote")->assertNotFound();
    });

    it('refuses to support an idea already merged away', function () {
        $target = Idea::factory()->create();
        $duplicate = Idea::factory()->create(['merged_into_id' => $target->id]);

        $this->actingAs(ideaContributor())->postJson("/api/ideas/{$duplicate->id}/vote")->assertNotFound();
    });
});

describe('DELETE /api/ideas/{id}/vote', function () {
    it('withdraws support and takes the count back down', function () {
        $user = ideaContributor();
        $idea = Idea::factory()->create();

        $this->actingAs($user)->postJson("/api/ideas/{$idea->id}/vote");
        $response = $this->actingAs($user)->deleteJson("/api/ideas/{$idea->id}/vote");

        $response->assertOk();
        expect($response->json('data.votesCount'))->toBe(0);
        expect($response->json('data.voted'))->toBeFalse();
    });

    it('is harmless when the person never supported it', function () {
        $idea = Idea::factory()->create();

        $response = $this->actingAs(ideaContributor())->deleteJson("/api/ideas/{$idea->id}/vote");

        $response->assertOk();
        expect($response->json('data.votesCount'))->toBe(0);
    });

    it('takes away only the sender own support', function () {
        $first = ideaContributor();
        $second = ideaContributor();
        $idea = Idea::factory()->create();

        $this->actingAs($first)->postJson("/api/ideas/{$idea->id}/vote");
        $this->actingAs($second)->postJson("/api/ideas/{$idea->id}/vote");
        $response = $this->actingAs($first)->deleteJson("/api/ideas/{$idea->id}/vote");

        expect($response->json('data.votesCount'))->toBe(1);
    });
});
