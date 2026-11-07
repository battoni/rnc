<?php

use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('GET /api/news', function () {
    it('shows a contributor the published entries only', function () {
        News::factory()->create(['title' => 'Publicada']);
        News::factory()->draft()->create(['title' => 'Rascunho']);

        $entries = $this->actingAs(ideaContributor())->getJson('/api/news')->json('data');

        expect($entries)->toHaveCount(1);
        expect($entries[0]['title'])->toBe('Publicada');
    });

    it('shows the maintainer their drafts too', function () {
        News::factory()->create(['title' => 'Publicada']);
        News::factory()->draft()->create(['title' => 'Rascunho']);

        $entries = $this->actingAs(newsWriter())->getJson('/api/news')->json('data');

        expect($entries)->toHaveCount(2);
    });

    it('credits the idea and its author when the entry answers one', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->create(['title' => 'Exportar em CSV']);
        News::factory()->create(['title' => 'Relatório exporta CSV', 'idea_id' => $idea->id]);

        $entry = $this->actingAs(ideaContributor())->getJson('/api/news')->json('data.0');

        expect($entry['idea']['title'])->toBe('Exportar em CSV');
        expect($entry['idea']['author']['id'])->toBe($author->id);
    });

    it('carries a null idea for the work nobody asked for, which is most of it', function () {
        News::factory()->create(['title' => 'Modo escuro repensado', 'idea_id' => null]);

        expect($this->actingAs(ideaContributor())->getJson('/api/news')->json('data.0.idea'))->toBeNull();
    });

    it('puts the newest entry first', function () {
        News::factory()->create(['title' => 'Older', 'published_at' => now()->subMonth()]);
        News::factory()->create(['title' => 'Recente', 'published_at' => now()]);

        expect($this->actingAs(ideaContributor())->getJson('/api/news')->json('data.0.title'))->toBe('Recente');
    });
});

describe('POST /api/news', function () {
    it('publishes when asked to and holds as a draft otherwise', function () {
        $maintainer = newsWriter();

        $published = $this->actingAs($maintainer)->postJson('/api/news', [
            'title' => 'Sai agora',
            'body' => '<p>Corpo.</p>',
            'publish' => true,
        ]);

        $draft = $this->actingAs($maintainer)->postJson('/api/news', [
            'title' => 'Fica guardada',
            'body' => '<p>Corpo.</p>',
        ]);

        expect($published->json('data.published'))->toBeTrue();
        expect($draft->json('data.published'))->toBeFalse();
        expect($draft->json('data.publishedAt'))->toBeNull();
    });

    it('is closed to a contributor', function () {
        $this->actingAs(ideaContributor())->postJson('/api/news', [
            'title' => 'Should not get in',
            'body' => '<p>Corpo.</p>',
        ])->assertForbidden();

        expect(News::count())->toBe(0);
    });

    it('refuses to credit an idea that does not exist', function () {
        $this->actingAs(newsWriter())->postJson('/api/news', [
            'title' => 'Título',
            'body' => '<p>Corpo.</p>',
            'ideaId' => 9999,
        ])->assertUnprocessable()->assertJsonValidationErrors('ideaId');
    });
});

describe('PATCH /api/news/{id}', function () {
    it('keeps the date an entry went out with when it is edited again', function () {
        $news = News::factory()->create(['published_at' => now()->subWeek()]);

        $response = $this->actingAs(newsWriter())->patchJson("/api/news/{$news->id}", [
            'title' => 'Título corrigido',
            'body' => '<p>Corpo corrigido.</p>',
            'publish' => true,
        ]);

        expect($response->json('data.publishedAt'))->toBe($news->published_at->toIso8601String());
        expect($response->json('data.title'))->toBe('Título corrigido');
    });

    it('can pull a published entry back to a draft', function () {
        $news = News::factory()->create();

        $response = $this->actingAs(newsWriter())->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => $news->body,
            'publish' => false,
        ]);

        expect($response->json('data.published'))->toBeFalse();
    });
});

describe('DELETE /api/news/{id}', function () {
    it('removes the entry for the maintainer and refuses a contributor', function () {
        $news = News::factory()->create();

        $this->actingAs(ideaContributor())->deleteJson("/api/news/{$news->id}")->assertForbidden();
        $this->actingAs(newsWriter())->deleteJson("/api/news/{$news->id}")->assertOk();

        expect(News::count())->toBe(0);
    });
});

/*
 * The list groups by the date it displays, which for a draft is when it was written. Ordering by
 * `published_at` alone puts NULLs last, so a draft written today sank below last month's entries.
 */
it('sorts a draft by the date the screen shows for it', function () {
    News::factory()->create(['title' => 'Publicada', 'published_at' => now()->subMonth()]);
    News::factory()->draft()->create(['title' => 'Rascunho de hoje', 'created_at' => now()]);

    $titles = collect($this->actingAs(newsWriter())->getJson('/api/news')->json('data'))
        ->pluck('title')
        ->all();

    expect($titles)->toBe(['Rascunho de hoje', 'Publicada']);
});
