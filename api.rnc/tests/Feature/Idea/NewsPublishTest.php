<?php

use App\Modules\Idea\Models\News;

/*
 * The publish switch is the reason this endpoint exists rather than a reuse of the full update:
 * it must be impossible to lose a field by flipping a boolean.
 */
it('publishes a draft without touching anything else', function () {
    $writer = newsWriter();
    $draft = News::factory()->create([
        'title' => 'Entries gain a subtitlospectiva guiada',
        'subtitle' => 'Um roteiro de quatro perguntas',
        'body' => '<p>Rascunho.</p>',
        'link' => 'https://example.test',
        'published_at' => null,
        'is_published' => false,
    ]);

    $response = $this->actingAs($writer)->patchJson("/api/news/{$draft->id}/publish", ['publish' => true]);

    $response->assertOk()
        ->assertJsonPath('data.published', true)
        ->assertJsonPath('data.subtitle', 'Um roteiro de quatro perguntas')
        ->assertJsonPath('data.link', 'https://example.test')
        ->assertJsonPath('data.body', '<p>Rascunho.</p>');

    expect($draft->fresh()->is_published)->toBeTrue();
});

it('pulls a published entry back to draft', function () {
    $writer = newsWriter();
    $entry = News::factory()->create(['published_at' => now()->subDay(), 'is_published' => true]);

    $this->actingAs($writer)
        ->patchJson("/api/news/{$entry->id}/publish", ['publish' => false])
        ->assertOk()
        ->assertJsonPath('data.published', false);

    expect($entry->fresh()->is_published)->toBeFalse();
});

/*
 * A changelog that re-dates itself is not a record. Pulling an entry back and pushing it out again
 * must not move the date it originally went out with.
 */
it('keeps the original publication date when an entry goes back out', function () {
    $writer = newsWriter();
    $publishedAt = now()->subMonth()->startOfDay();
    $entry = News::factory()->create(['published_at' => $publishedAt, 'is_published' => true]);

    $this->actingAs($writer)->patchJson("/api/news/{$entry->id}/publish", ['publish' => false])->assertOk();
    $this->actingAs($writer)->patchJson("/api/news/{$entry->id}/publish", ['publish' => true])->assertOk();

    expect($entry->fresh()->published_at->timestamp)->toBe($publishedAt->timestamp);
});

it('refuses a reader who may not write the changelog', function () {
    $entry = News::factory()->draft()->create();

    $this->actingAs(ideaContributor())
        ->patchJson("/api/news/{$entry->id}/publish", ['publish' => true])
        ->assertForbidden();

    expect($entry->fresh()->is_published)->toBeFalse();
});

it('requires the boolean it is named after', function () {
    $entry = News::factory()->create();

    $this->actingAs(newsWriter())
        ->patchJson("/api/news/{$entry->id}/publish", [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('publish');
});

it('answers 404 for an entry that is not there', function () {
    $this->actingAs(newsWriter())
        ->patchJson('/api/news/999999/publish', ['publish' => true])
        ->assertNotFound();
});

/*
 * The full update writes `is_published` from its `publish` field unconditionally, so an absent
 * field cannot be allowed to mean "unpublish": a valid request that simply omitted it used to pull
 * a live article off every reader's list.
 */
it('refuses a full update that does not say whether the entry is published', function () {
    $entry = News::factory()->create();

    $this->actingAs(newsWriter())
        ->patchJson("/api/news/{$entry->id}", ['title' => 'Novo título', 'body' => '<p>Corpo.</p>'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('publish');

    expect($entry->fresh()->is_published)->toBeTrue();
});
