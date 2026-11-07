<?php

use App\Modules\Idea\Models\News;
use App\Modules\Idea\Models\NewsReaction;
use App\Modules\Idea\Models\NewsRead;

describe('POST /api/news/{id}/read', function () {
    it('records who read an entry, and when', function () {
        $reader = ideaContributor();
        $entry = News::factory()->create();

        $this->actingAs($reader)->postJson("/api/news/{$entry->id}/read")
            ->assertOk()
            ->assertJsonPath('data.read', true);

        $read = NewsRead::where(['news_id' => $entry->id, 'user_id' => $reader->id])->first();
        expect($read)->not->toBeNull()
            ->and($read->read_at)->not->toBeNull();
    });

    /*
     * "When did they first read it" is the useful fact. Re-opening an entry a month later says
     * nothing new, and overwriting the timestamp would destroy the only answer to "did anyone read
     * this when I published it".
     */
    it('keeps the first read and never duplicates or re-dates it', function () {
        $reader = ideaContributor();
        $entry = News::factory()->create();

        $this->actingAs($reader)->postJson("/api/news/{$entry->id}/read")->assertOk();
        $firstReadAt = NewsRead::where('news_id', $entry->id)->first()->read_at;

        $this->travel(2)->days();
        $this->actingAs($reader)->postJson("/api/news/{$entry->id}/read")->assertOk();

        expect(NewsRead::where('news_id', $entry->id)->count())->toBe(1)
            ->and(NewsRead::where('news_id', $entry->id)->first()->read_at->timestamp)
            ->toBe($firstReadAt->timestamp);
    });

    /* The writer reading their own unpublished draft is not an audience. */
    it('refuses to mark a draft read', function () {
        $entry = News::factory()->draft()->create();

        $this->actingAs(newsWriter())->postJson("/api/news/{$entry->id}/read")->assertNotFound();

        expect(NewsRead::count())->toBe(0);
    });
});

describe('the unread dot', function () {
    it('counts the published entries this person has not opened', function () {
        $reader = ideaContributor();
        $read = News::factory()->create();
        News::factory()->count(2)->create();
        News::factory()->draft()->create();

        $this->actingAs($reader)->postJson("/api/news/{$read->id}/read")->assertOk();

        $this->actingAs($reader)->getJson('/api/news/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread', 2);
    });

    /* A draft nobody can see must never light the dot. */
    it('never counts a draft', function () {
        News::factory()->draft()->count(3)->create();

        $this->actingAs(ideaContributor())->getJson('/api/news/unread-count')
            ->assertOk()
            ->assertJsonPath('data.unread', 0);
    });

    it('is each person\'s own count', function () {
        $first = ideaContributor();
        $entry = News::factory()->create();

        $this->actingAs($first)->postJson("/api/news/{$entry->id}/read")->assertOk();

        $this->actingAs($first)->getJson('/api/news/unread-count')->assertJsonPath('data.unread', 0);
        $this->actingAs(ideaContributor())->getJson('/api/news/unread-count')->assertJsonPath('data.unread', 1);
    });
});

describe('GET /api/news/{id}/readers', function () {
    it('names who read the entry, newest first', function () {
        $entry = News::factory()->create();
        $early = ideaContributor();
        $late = ideaContributor();

        $this->actingAs($early)->postJson("/api/news/{$entry->id}/read")->assertOk();
        $this->travel(1)->hour();
        $this->actingAs($late)->postJson("/api/news/{$entry->id}/read")->assertOk();

        $response = $this->actingAs(newsWriter())->getJson("/api/news/{$entry->id}/readers")->assertOk();

        expect($response->json('data.0.user.id'))->toBe($late->id)
            ->and($response->json('data.1.user.id'))->toBe($early->id);
    });

    /*
     * A changelog that told every reader who else had read it would be publishing the audience's
     * behaviour to the audience.
     */
    it('is closed to a reader', function () {
        $entry = News::factory()->create();

        $this->actingAs(ideaContributor())->getJson("/api/news/{$entry->id}/readers")->assertForbidden();
    });
});

describe('POST /api/news/{id}/reactions', function () {
    it('counts applause and hands back the running total', function () {
        $entry = News::factory()->create();

        $this->actingAs(ideaContributor())->postJson("/api/news/{$entry->id}/reactions", ['type' => 'clap'])
            ->assertOk()
            ->assertJsonPath('data.reactions.clap', 1)
            ->assertJsonPath('data.reactions.heart', 0)
            ->assertJsonPath('data.reactions.rocket', 0);
    });

    /* A vote is a judgement and must be one per person. This is applause; clapping twice is the point. */
    it('lets one person clap as many times as they like', function () {
        $reader = ideaContributor();
        $entry = News::factory()->create();

        foreach (range(1, 5) as $ignored) {
            $this->actingAs($reader)->postJson("/api/news/{$entry->id}/reactions", ['type' => 'clap'])->assertOk();
        }

        $response = $this->actingAs($reader)->getJson('/api/news');

        expect($response->json('data.0.reactions.clap'))->toBe(5)
            ->and($response->json('data.0.myReactions.clap'))->toBe(5)
            ->and(NewsReaction::where('news_id', $entry->id)->count())->toBe(1);
    });

    /* The total is the room; myReactions is your own share of it. The client needs both. */
    it('separates the room from the person', function () {
        $mine = ideaContributor();
        $entry = News::factory()->create();

        $this->actingAs($mine)->postJson("/api/news/{$entry->id}/reactions", ['type' => 'rocket'])->assertOk();
        $this->actingAs(ideaContributor())->postJson("/api/news/{$entry->id}/reactions", ['type' => 'rocket'])->assertOk();
        $this->actingAs(ideaContributor())->postJson("/api/news/{$entry->id}/reactions", ['type' => 'rocket'])->assertOk();

        $response = $this->actingAs($mine)->getJson('/api/news');

        expect($response->json('data.0.reactions.rocket'))->toBe(3)
            ->and($response->json('data.0.myReactions.rocket'))->toBe(1);
    });

    it('refuses a reaction it does not have', function () {
        $entry = News::factory()->create();

        $this->actingAs(ideaContributor())->postJson("/api/news/{$entry->id}/reactions", ['type' => 'thumbsdown'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    });

    it('refuses applause for a draft', function () {
        $entry = News::factory()->draft()->create();

        $this->actingAs(newsWriter())->postJson("/api/news/{$entry->id}/reactions", ['type' => 'clap'])
            ->assertNotFound();
    });
});

describe('the read count on the list', function () {
    it('shows the writer how many people read each entry', function () {
        $entry = News::factory()->create();
        $this->actingAs(ideaContributor())->postJson("/api/news/{$entry->id}/read")->assertOk();
        $this->actingAs(ideaContributor())->postJson("/api/news/{$entry->id}/read")->assertOk();

        $this->actingAs(newsWriter())->getJson('/api/news')
            ->assertOk()
            ->assertJsonPath('data.0.readCount', 2);
    });

    it('does not tell a reader how many people read it', function () {
        $entry = News::factory()->create();
        $this->actingAs(ideaContributor())->postJson("/api/news/{$entry->id}/read")->assertOk();

        $this->actingAs(ideaContributor())->getJson('/api/news')
            ->assertOk()
            ->assertJsonMissingPath('data.0.readCount');
    });
});
