<?php

use App\Modules\Idea\Enums\IdeaArea;
use App\Modules\Idea\Enums\IdeaStatus;
use App\Modules\Idea\Enums\IdeaType;
use App\Modules\Idea\Models\Idea;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('POST /api/ideas', function () {
    it('records an idea and returns it as received, authored by the sender', function () {
        $user = ideaContributor();

        $response = $this->actingAs($user)->postJson('/api/ideas', [
            'title' => 'Duplicate a block by dragging with Alt',
            'description' => 'Almost every block I place is the same as one already there.',
            'type' => IdeaType::IMPROVEMENT->value,
            'area' => IdeaArea::GENERAL->value,
        ]);

        $response->assertStatus(201);
        expect($response->json('data.status'))->toBe(IdeaStatus::RECEIVED->value);
        expect($response->json('data.votesCount'))->toBe(0);
        expect($response->json('data.voted'))->toBeFalse();
        expect($response->json('data.author.id'))->toBe($user->id);
    });

    it('defaults a missing area to general rather than refusing the idea', function () {
        $response = $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'An idea with no area',
            'description' => 'Did not want to classify it.',
            'type' => IdeaType::IDEA->value,
        ]);

        $response->assertStatus(201);
        expect($response->json('data.area'))->toBe(IdeaArea::GENERAL->value);
    });

    it('refuses an unknown type and an unknown area', function () {
        $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'Título',
            'description' => 'Descrição',
            'type' => 'fantasma',
            'area' => 'nenhures',
        ])->assertUnprocessable()->assertJsonValidationErrors(['type', 'area']);
    });

    it('refuses a title longer than the column', function () {
        $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => str_repeat('a', 121),
            'description' => 'Descrição',
            'type' => IdeaType::IDEA->value,
        ])->assertUnprocessable()->assertJsonValidationErrors('title');
    });
});

describe('GET /api/ideas', function () {
    it('shows every contributor the ideas of every other contributor', function () {
        $author = ideaContributor();
        $reader = ideaContributor();
        Idea::factory()->for($author, 'user')->create(['title' => 'Somebody else idea']);

        $response = $this->actingAs($reader)->getJson('/api/ideas');

        $response->assertOk();
        expect($response->json('data.0.title'))->toBe('Somebody else idea');
    });

    it('orders by support by default and by recency on request', function () {
        $user = ideaContributor();
        Idea::factory()->create(['title' => 'Pouco apoiada', 'votes_count' => 1, 'created_at' => now()]);
        Idea::factory()->create(['title' => 'Muito apoiada', 'votes_count' => 9, 'created_at' => now()->subDay()]);

        expect($this->actingAs($user)->getJson('/api/ideas')->json('data.0.title'))
            ->toBe('Muito apoiada');

        expect($this->actingAs($user)->getJson('/api/ideas?sort=recent')->json('data.0.title'))
            ->toBe('Pouco apoiada');
    });

    it('narrows by status, type, area and free text', function () {
        $user = ideaContributor();
        Idea::factory()->create([
            'title' => 'Erro no total da semana',
            'type' => IdeaType::PROBLEM,
            'area' => IdeaArea::HOME,
            'status' => IdeaStatus::IN_PROGRESS,
        ]);
        Idea::factory()->create(['title' => 'Outra coisa qualquer']);

        expect($this->actingAs($user)->getJson('/api/ideas?status=in_progress')->json('data'))->toHaveCount(1);
        expect($this->actingAs($user)->getJson('/api/ideas?type=problem')->json('data'))->toHaveCount(1);
        expect($this->actingAs($user)->getJson('/api/ideas?area=home')->json('data'))->toHaveCount(1);
        expect($this->actingAs($user)->getJson('/api/ideas?search=total da semana')->json('data'))->toHaveCount(1);
    });

    it('narrows to the sender own ideas with mine', function () {
        $user = ideaContributor();
        Idea::factory()->for($user, 'user')->create(['title' => 'Minha']);
        Idea::factory()->create(['title' => 'De outra pessoa']);

        $response = $this->actingAs($user)->getJson('/api/ideas?mine=1');

        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.title'))->toBe('Minha');
    });

    it('keeps an idea readable after its author is deleted, with a null author', function () {
        $author = ideaContributor();
        Idea::factory()->for($author, 'user')->create(['title' => 'Sobrevive ao autor']);

        $author->delete();

        $response = $this->actingAs(ideaContributor())->getJson('/api/ideas');

        expect($response->json('data'))->toHaveCount(1);
        expect($response->json('data.0.title'))->toBe('Sobrevive ao autor');
        expect($response->json('data.0.author'))->toBeNull();
    });
});
