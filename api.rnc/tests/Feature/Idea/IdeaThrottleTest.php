<?php

use App\Modules\Idea\Enums\IdeaType;
use App\Modules\Idea\Models\Idea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('ideas|user|1');
});

/*
 * The board is the one endpoint in the product where one account writes text every other account
 * reads. A ceiling is not about abuse in the abstract: without it a single script fills the board
 * and it stops being readable for everyone else.
 */
describe('the write ceiling', function () {
    it('stops a sixth new idea within the hour', function () {
        $user = ideaContributor();

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($user)->postJson('/api/ideas', [
                'title' => "Idea {$attempt}",
                'description' => 'Descrição.',
                'type' => IdeaType::IDEA->value,
            ])->assertStatus(201);
        }

        $this->actingAs($user)->postJson('/api/ideas', [
            'title' => 'Idea six',
            'description' => 'Descrição.',
            'type' => IdeaType::IDEA->value,
        ])->assertStatus(429);

        expect(Idea::count())->toBe(5);
    });

    /*
     * Comments do NOT share that ceiling, and the reason is the maintainer: the person most likely
     * to write many comments in an hour is the one answering the board, and five would have locked
     * them out on the sixth reply. A new idea is a rare act; a reply is a conversation.
     */
    it('lets somebody hold a conversation without hitting the idea ceiling', function () {
        $user = ideaMaintainer();
        $idea = Idea::factory()->create();

        foreach (range(1, 12) as $attempt) {
            $this->actingAs($user)
                ->postJson("/api/ideas/{$idea->id}/comments", ['body' => "Resposta {$attempt}."])
                ->assertStatus(201);
        }

        expect($idea->fresh()->comments_count)->toBe(12);
    });

    it('counts the ceiling per account, not per address', function () {
        $first = ideaContributor();
        $second = ideaContributor();

        foreach (range(1, 5) as $attempt) {
            $this->actingAs($first)->postJson('/api/ideas', [
                'title' => "Idea {$attempt}",
                'description' => 'Descrição.',
                'type' => IdeaType::IDEA->value,
            ])->assertStatus(201);
        }

        // The second account is untouched by the first one's spending.
        $this->actingAs($second)->postJson('/api/ideas', [
            'title' => 'From another account',
            'description' => 'Descrição.',
            'type' => IdeaType::IDEA->value,
        ])->assertStatus(201);
    });
});
