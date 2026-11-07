<?php

use App\Modules\Idea\Models\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The share link answers a crawler that will never run JavaScript, and a person who wants the
 * article. One URL, two audiences.
 */

describe('the public share page', function () {
    it('carries the entry in its head, where a crawler can read it', function () {
        $news = News::factory()->create([
            'title' => 'The board: following a whole year',
            'subtitle' => 'Um curso, um treino, um idioma.',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->get("/n/{$news->id}");

        $response->assertOk();
        $response->assertSee('<meta property="og:title" content="The board: following a whole year">', false);
        $response->assertSee('<meta property="og:description" content="Um curso, um treino, um idioma.">', false);
    });

    /*
     * The key is the one the SPA reads (`SHARED_ENTRY_QUERY_KEY` in app.rnc's NewsView). This page
     * sent `?entry=` while the SPA read `?entrada=` (overnight review, 2026-09-17): the link from
     * WhatsApp landed on the list and the article never opened.
     */
    it('points the reader at the entry inside the app, under the key the app reads', function () {
        $news = News::factory()->create(['is_published' => true, 'published_at' => now()]);

        $this->get("/n/{$news->id}")
            ->assertSee(config('rnc.frontend_url')."/news?entrada={$news->id}", false)
            ->assertDontSee('/news?entry=', false);
    });

    it('offers the cover as the preview image, absolute', function () {
        Storage::fake('public');

        $news = News::factory()->create(['is_published' => true, 'published_at' => now()]);
        $news->cover()->create([
            'disk' => 'public',
            'path' => 'news/capa.png',
            'mime' => 'image/png',
            'size' => 100,
        ]);

        $response = $this->get("/n/{$news->id}");

        $response->assertSee('og:image', false);
        // Relative would resolve against the crawler's own host, which is nobody's site.
        $response->assertSee('http', false);
        $response->assertSee('twitter:card" content="summary_large_image', false);
    });

    /*
     * A draft is answered 404, not 403. Saying "this exists but is not published" leaks the fact
     * that the writer is working on something, to anyone who guesses an id.
     */
    it('refuses a draft as if it were not there', function () {
        $news = News::factory()->create(['is_published' => false, 'published_at' => null]);

        $this->get("/n/{$news->id}")->assertNotFound();
    });

    it('refuses an id that names nothing', function () {
        $this->get('/n/999999')->assertNotFound();
    });

    it('needs no session, because a crawler has none', function () {
        $news = News::factory()->create(['is_published' => true, 'published_at' => now()]);

        // No actingAs anywhere in this file, and this is the assertion that says so on purpose.
        $this->get("/n/{$news->id}")->assertOk();
    });
});
