<?php

use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaComment;
use App\Modules\Idea\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The client's TypeScript interfaces are written against these exact keys, and nothing else checks
 * that the two still agree: every e2e spec route-intercepts the API, so a field renamed here would
 * pass the whole frontend suite and break only in a browser.
 *
 * These assertions are on the EXACT key set, not on presence. A key that quietly appears is as much
 * a contract change as one that disappears — it means the resource grew something the client is not
 * reading, which is either dead weight on the wire or a client that was never updated.
 *
 * Verified against the running API before being written down: `src/modules/Idea/interfaces.ts`
 * matched all three shapes exactly.
 */
describe('the shape the client is written against', function () {
    it('sends an idea with exactly the keys the client reads', function () {
        Idea::factory()->create();

        $idea = $this->actingAs(ideaContributor())->getJson('/api/ideas')->json('data.0');

        expect(array_keys($idea))->toEqualCanonicalizing([
            'id', 'title', 'description', 'type', 'area', 'status', 'adminNote',
            'votesCount', 'commentsCount', 'voted', 'canEdit', 'hidden',
            'mergedIntoId', 'deliveredAt', 'createdAt', 'author', 'attachment',
        ]);
    });

    it('adds comments — and only comments — when one idea is asked for', function () {
        $idea = Idea::factory()->create();

        $shown = $this->actingAs(ideaContributor())->getJson("/api/ideas/{$idea->id}")->json('data');

        expect(array_keys($shown))->toEqualCanonicalizing([
            'id', 'title', 'description', 'type', 'area', 'status', 'adminNote',
            'votesCount', 'commentsCount', 'voted', 'canEdit', 'hidden',
            'mergedIntoId', 'deliveredAt', 'createdAt', 'author', 'attachment', 'comments',
        ]);
    });

    /*
     * Ids used to travel as strings here while every other module sent numbers, and the client
     * carried both shapes. One format on the wire (lifeshifter-269): integers, everywhere.
     */
    it('sends ids as integers, like every other resource', function () {
        $idea = Idea::factory()->create();
        IdeaComment::factory()->for($idea)->create();

        $shown = $this->actingAs(ideaContributor())->getJson("/api/ideas/{$idea->id}")->json('data');

        expect($shown['id'])->toBeInt();
        expect($shown['author']['id'])->toBeInt();
        expect($shown['comments'][0]['id'])->toBeInt();
        expect($shown['comments'][0]['ideaId'])->toBeInt();
    });

    it('sends the foreign id of a merged idea as an integer too', function () {
        $target = Idea::factory()->create();
        $merged = Idea::factory()->create(['merged_into_id' => $target->id]);

        $shown = $this->actingAs(ideaMaintainer())->getJson("/api/ideas/{$merged->id}")->json('data');

        expect($shown['mergedIntoId'])->toBe($target->id);
    });

    it('sends counts as integers and flags as booleans', function () {
        Idea::factory()->supported(3)->create();

        $idea = $this->actingAs(ideaContributor())->getJson('/api/ideas')->json('data.0');

        expect($idea['votesCount'])->toBeInt();
        expect($idea['commentsCount'])->toBeInt();
        expect($idea['voted'])->toBeBool();
        expect($idea['canEdit'])->toBeBool();
        expect($idea['hidden'])->toBeBool();
    });

    it('sends a comment with exactly the keys the client reads', function () {
        $idea = Idea::factory()->create();
        IdeaComment::factory()->for($idea)->create();

        $comment = $this->actingAs(ideaContributor())->getJson("/api/ideas/{$idea->id}/comments")->json('data.0');

        expect(array_keys($comment))->toEqualCanonicalizing([
            'id', 'ideaId', 'body', 'isOfficial', 'hidden', 'deleted', 'createdAt', 'author',
        ]);
    });

    it('sends a news entry with exactly the keys the client reads', function () {
        $idea = Idea::factory()->create();
        News::factory()->create(['idea_id' => $idea->id]);

        $entry = $this->actingAs(ideaContributor())->getJson('/api/news')->json('data.0');

        expect(array_keys($entry))->toEqualCanonicalizing([
            'id', 'title', 'subtitle', 'body', 'link', 'cover', 'areas',
            'published', 'publishedAt', 'createdAt', 'idea',
            // Engagement. `readCount` is absent on purpose — a reader is not told the audience.
            'reactions', 'myReactions', 'read',
        ]);
        expect(array_keys($entry['reactions']))->toEqualCanonicalizing(['clap', 'heart', 'rocket']);
        expect(array_keys($entry['myReactions']))->toEqualCanonicalizing(['clap', 'heart', 'rocket']);
        expect(array_keys($entry['idea']))->toEqualCanonicalizing(['id', 'title', 'author']);
        expect(array_keys($entry['idea']['author']))->toEqualCanonicalizing(['id', 'name']);
    });

    /*
     * A write answers with the same entry a read does, engagement included.
     *
     * The four engagement fields are published under `when(isset(...))`, so an entry that never
     * met the aggregates does not carry them wrong — it does not carry them AT ALL. The client
     * patches its list from this response, so `read` arriving as `undefined` put a freshly
     * created entry under neither "unread" nor "read" until the page was reloaded.
     */
    it('answers every news write with the engagement fields a read carries', function () {
        $writer = newsWriter();
        $idea = Idea::factory()->create();

        $created = $this->actingAs($writer)->postJson('/api/news', [
            'title' => 'Nova',
            'body' => '<p>Corpo.</p>',
            'ideaId' => $idea->id,
        ])->json('data');

        $updated = $this->actingAs($writer)->patchJson("/api/news/{$created['id']}", [
            'title' => 'Nova, revisada',
            'body' => '<p>Corpo.</p>',
            'publish' => true,
        ])->json('data');

        $published = $this->actingAs($writer)
            ->patchJson("/api/news/{$created['id']}/publish", ['publish' => false])
            ->json('data');

        foreach ([$created, $updated, $published] as $entry) {
            expect($entry)->toHaveKeys(['reactions', 'myReactions', 'read', 'readCount']);
            expect($entry['read'])->toBeFalse();
        }
    });

    it('sends an attachment with exactly the keys the client reads', function () {
        Storage::fake('public');

        $created = $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'With an image',
            'description' => 'Descrição',
            'type' => 'problem',
            'attachment' => UploadedFile::fake()->image('print.png'),
        ]);

        expect(array_keys($created->json('data.attachment')))->toEqualCanonicalizing(['url', 'mime', 'size']);
    });

    it('sends a news cover with exactly the keys the client reads', function () {
        Storage::fake('public');

        $created = $this->actingAs(newsWriter())->postJson('/api/news', [
            'title' => 'With a cover',
            'body' => '<p>Corpo.</p>',
            'cover' => UploadedFile::fake()->image('capa.png'),
        ]);

        expect(array_keys($created->json('data.cover')))->toEqualCanonicalizing(['url', 'mime', 'size']);
    });

    /* A removed account leaves the row behind, and the client renders that as a removed contributor. */
    it('sends a null author rather than omitting the key', function () {
        $author = ideaContributor();
        Idea::factory()->for($author, 'user')->create();

        $author->delete();

        $idea = $this->actingAs(ideaContributor())->getJson('/api/ideas')->json('data.0');

        expect($idea)->toHaveKey('author');
        expect($idea['author'])->toBeNull();
    });
});
