<?php

use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\News;
use App\Modules\Idea\Models\NewsCover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The body is HTML from a rich-text editor and is STORED as HTML, so the sanitiser is the only
 * thing between an editor and stored XSS. These assert what reaches the DATABASE, not what a screen
 * chooses to render: cleaning at render time protects one screen and leaves every other consumer
 * of the API holding whatever was pasted.
 */
describe('the article body is sanitised on the way in', function () {
    function postArticle(array $overrides = []): array
    {
        $response = test()->actingAs(newsWriter())->postJson('/api/news', array_merge([
            'title' => 'An entry',
            'body' => '<p>Corpo.</p>',
        ], $overrides));

        $response->assertStatus(201);

        return [News::latest('id')->first()->body, $response];
    }

    it('keeps the markup a writer actually uses', function () {
        [$stored] = postArticle(['body' => '<p>A <strong>point</strong> with <em>emphasis</em>.</p><ul><li>item</li></ul>']);

        expect($stored)->toContain('<strong>point</strong>');
        expect($stored)->toContain('<em>emphasis</em>');
        expect($stored)->toContain('<li>item</li>');
    });

    it('strips a script tag', function () {
        [$stored] = postArticle(['body' => '<p>ok</p><script>alert(1)</script>']);

        expect($stored)->toContain('ok');
        expect($stored)->not->toContain('script');
        expect($stored)->not->toContain('alert');
    });

    it('strips an inline event handler', function () {
        [$stored] = postArticle(['body' => '<p onclick="alert(1)">text</p>']);

        expect($stored)->toContain('text');
        expect($stored)->not->toContain('onclick');
    });

    /* `javascript:` is the scheme that turns a link into a script. */
    it('drops a javascript href but keeps the words', function () {
        [$stored] = postArticle(['body' => '<a href="javascript:alert(1)">clique</a>']);

        expect($stored)->toContain('clique');
        expect($stored)->not->toContain('javascript');
    });

    it('keeps an ordinary https link', function () {
        [$stored] = postArticle(['body' => '<a href="https://example.com">link</a>']);

        expect($stored)->toContain('https://example.com');
    });

    /*
     * Images are refused in the BODY on purpose: an entry has a cover, uploaded through this API
     * and served from a disk we control. An `<img>` in the body is a remote URL nobody vetted,
     * loading from a third party in every reader's browser and telling that third party who read
     * what.
     */
    it('refuses an image in the body, which the cover exists for', function () {
        [$stored] = postArticle(['body' => '<p>before</p><img src="https://thirdparty.test/pixel.png"><p>after</p>']);

        expect($stored)->toContain('before');
        expect($stored)->toContain('after');
        expect($stored)->not->toContain('img');
        expect($stored)->not->toContain('thirdparty.test');
    });

    it('sanitises on edit too, not only on create', function () {
        $news = News::factory()->create();

        $this->actingAs(newsWriter())->patchJson("/api/news/{$news->id}", [
            'title' => 'Editada',
            'body' => '<p>ok</p><script>alert(1)</script>',
            'publish' => true,
        ])->assertOk();

        expect($news->fresh()->body)->not->toContain('script');
    });
});

describe('the cover', function () {
    beforeEach(fn () => Storage::fake('public'));

    it('is stored and comes back as a url', function () {
        $response = $this->actingAs(newsWriter())->postJson('/api/news', [
            'title' => 'With a cover',
            'body' => '<p>Corpo.</p>',
            'cover' => UploadedFile::fake()->image('capa.png', 1200, 630),
        ]);

        $response->assertStatus(201);
        expect($response->json('data.cover.url'))->toContain('news/');
        expect(NewsCover::count())->toBe(1);
        Storage::disk('public')->assertExists(NewsCover::first()->path);
    });

    /* The same rules as an idea's screenshot, because they are the same code — see ImageAttachmentService. */
    it('refuses an SVG, which is markup that runs script', function () {
        $path = tempnam(sys_get_temp_dir(), 'cover').'.png';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs(newsWriter())->postJson('/api/news', [
            'title' => 'With an svg',
            'body' => '<p>Corpo.</p>',
            'cover' => new UploadedFile($path, 'capa.png', null, null, true),
        ])->assertUnprocessable()->assertJsonValidationErrors('cover');

        expect(NewsCover::count())->toBe(0);
    });

    it('never keeps the name the client sent', function () {
        $this->actingAs(newsWriter())->postJson('/api/news', [
            'title' => 'With a cover',
            'body' => '<p>Corpo.</p>',
            'cover' => UploadedFile::fake()->image('../../etc/passwd.png'),
        ])->assertStatus(201);

        expect(NewsCover::first()->path)->not->toContain('passwd');
        expect(NewsCover::first()->path)->not->toContain('..');
    });

    it('replaces the old file instead of piling a second one up', function () {
        $news = News::factory()->create();

        $this->actingAs(newsWriter())->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => '<p>Corpo.</p>',
            'cover' => UploadedFile::fake()->image('primeira.png'),
            'publish' => true,
        ])->assertOk();

        $firstPath = NewsCover::first()->path;

        $this->actingAs(newsWriter())->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => '<p>Corpo.</p>',
            'cover' => UploadedFile::fake()->image('segunda.png'),
            'publish' => true,
        ])->assertOk();

        expect(NewsCover::where('news_id', $news->id)->count())->toBe(1);
        Storage::disk('public')->assertMissing($firstPath);
    });

    it('is removed on request', function () {
        $news = News::factory()->create();

        $this->actingAs(newsWriter())->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => '<p>Corpo.</p>',
            'cover' => UploadedFile::fake()->image('capa.png'),
            'publish' => true,
        ]);

        $path = NewsCover::first()->path;

        $response = $this->actingAs(newsWriter())->patchJson("/api/news/{$news->id}", [
            'title' => $news->title,
            'body' => '<p>Corpo.</p>',
            'removeCover' => true,
            'publish' => true,
        ]);

        $response->assertOk();
        expect($response->json('data.cover'))->toBeNull();
        Storage::disk('public')->assertMissing($path);
    });

    it('leaves no orphan file when the entry is deleted', function () {
        $created = $this->actingAs(newsWriter())->postJson('/api/news', [
            'title' => 'Some junto',
            'body' => '<p>Corpo.</p>',
            'cover' => UploadedFile::fake()->image('capa.png'),
        ]);

        $path = NewsCover::first()->path;

        $this->actingAs(newsWriter())->deleteJson('/api/news/'.$created->json('data.id'))->assertOk();

        expect(NewsCover::count())->toBe(0);
        Storage::disk('public')->assertMissing($path);
    });
});

describe('the article fields', function () {
    it('round-trips a subtitle and a link', function () {
        $response = $this->actingAs(newsWriter())->postJson('/api/news', [
            'title' => 'Título',
            'subtitle' => 'The sentence that convinces someone to read',
            'body' => '<p>Corpo.</p>',
            'link' => 'https://example.test/news',
        ]);

        expect($response->json('data.subtitle'))->toBe('The sentence that convinces someone to read');
        expect($response->json('data.link'))->toBe('https://example.test/news');
    });

    /* The scheme is the whole risk in a link field. */
    it('refuses a link that is not http or https', function () {
        $this->actingAs(newsWriter())->postJson('/api/news', [
            'title' => 'Título',
            'body' => '<p>Corpo.</p>',
            'link' => 'javascript:alert(1)',
        ])->assertUnprocessable()->assertJsonValidationErrors('link');
    });

    it('lets an entry carry neither, because a short one earns neither', function () {
        $response = $this->actingAs(newsWriter())->postJson('/api/news', [
            'title' => 'Só o essencial',
            'body' => '<p>Corpo.</p>',
        ]);

        expect($response->json('data.subtitle'))->toBeNull();
        expect($response->json('data.link'))->toBeNull();
    });
});

/*
 * Writing the changelog and moderating the board became different jobs when Novidades grew a cover
 * and an article. The point of the split is what each key REFUSES.
 */
describe('the news.manage key', function () {
    it('lets a news writer publish without any power over the board', function () {
        $writer = newsWriter();

        $this->actingAs($writer)->postJson('/api/news', [
            'title' => 'Publicada',
            'body' => '<p>Corpo.</p>',
        ])->assertStatus(201);

        $idea = Idea::factory()->create();
        $this->actingAs($writer)->patchJson("/api/ideas/{$idea->id}/hide")->assertForbidden();
    });

    it('refuses an idea moderator who was never given the news key', function () {
        $this->actingAs(ideaMaintainer())->postJson('/api/news', [
            'title' => 'Should not get in',
            'body' => '<p>Corpo.</p>',
        ])->assertForbidden();

        expect(News::count())->toBe(0);
    });
});
