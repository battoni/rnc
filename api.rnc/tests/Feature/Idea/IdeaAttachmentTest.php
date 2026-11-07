<?php

use App\Modules\Idea\Enums\IdeaType;
use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaAttachment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

describe('an image attached to an idea', function () {
    it('is stored and comes back as a url on the idea', function () {
        $response = $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'A grade some no celular',
            'description' => 'Print em anexo.',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('print.png', 800, 600),
        ]);

        $response->assertStatus(201);
        expect($response->json('data.attachment.url'))->toContain('ideas/');
        expect($response->json('data.attachment.mime'))->toContain('image');
        expect(IdeaAttachment::count())->toBe(1);
        Storage::disk('public')->assertExists(IdeaAttachment::first()->path);
    });

    it('never keeps the name the client sent', function () {
        $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'Título',
            'description' => 'Descrição',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('../../etc/passwd.png'),
        ])->assertStatus(201);

        expect(IdeaAttachment::first()->path)->not->toContain('passwd');
        expect(IdeaAttachment::first()->path)->not->toContain('..');
    });

    it('refuses an SVG, which is markup that runs script', function () {
        $svg = UploadedFile::fake()->createWithContent(
            'payload.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'Título',
            'description' => 'Descrição',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => $svg,
        ])->assertUnprocessable()->assertJsonValidationErrors('attachment');

        expect(IdeaAttachment::count())->toBe(0);
    });

    /*
     * Built from a REAL file on disk rather than UploadedFile::fake(), and that is the whole
     * point of the test. The fake reports its MIME type from the file NAME, so a fake
     * 'shell.png' full of PHP passes every mime rule and the assertion would be testing
     * Laravel's test double instead of this endpoint. Passing null for the type makes Symfony
     * sniff the actual bytes, which is what happens to a real upload.
     */
    it('refuses a file that only pretends to be an image', function () {
        $path = tempnam(sys_get_temp_dir(), 'idea').'.png';
        file_put_contents($path, '<?php echo 1;');

        $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'Título',
            'description' => 'Descrição',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => new UploadedFile($path, 'shell.png', null, null, true),
        ])->assertUnprocessable()->assertJsonValidationErrors('attachment');

        expect(IdeaAttachment::count())->toBe(0);
    });

    it('refuses an SVG whose bytes are sniffed, not merely its extension', function () {
        $path = tempnam(sys_get_temp_dir(), 'idea').'.png';
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');

        $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'Título',
            'description' => 'Descrição',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => new UploadedFile($path, 'inocente.png', null, null, true),
        ])->assertUnprocessable()->assertJsonValidationErrors('attachment');

        expect(IdeaAttachment::count())->toBe(0);
    });

    it('only ever writes one of the four allowed extensions', function () {
        $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'Título',
            'description' => 'Descrição',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('print.png'),
        ])->assertStatus(201);

        expect(pathinfo(IdeaAttachment::first()->path, PATHINFO_EXTENSION))
            ->toBeIn(['jpg', 'jpeg', 'png', 'webp']);
    });

    it('refuses anything over two megabytes', function () {
        $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'Título',
            'description' => 'Descrição',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('grande.png')->size(2049),
        ])->assertUnprocessable()->assertJsonValidationErrors('attachment');
    });

    it('replaces the old file instead of piling a second one up', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->create();

        $this->actingAs($author)->patchJson("/api/ideas/{$idea->id}", [
            'title' => $idea->title,
            'description' => $idea->description,
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('primeiro.png'),
        ])->assertOk();

        $firstPath = IdeaAttachment::first()->path;

        $this->actingAs($author)->patchJson("/api/ideas/{$idea->id}", [
            'title' => $idea->title,
            'description' => $idea->description,
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('segundo.png'),
        ])->assertOk();

        expect(IdeaAttachment::where('idea_id', $idea->id)->count())->toBe(1);
        Storage::disk('public')->assertMissing($firstPath);
    });

    it('is removed on request without touching the rest of the idea', function () {
        $author = ideaContributor();
        $idea = Idea::factory()->for($author, 'user')->create();

        $this->actingAs($author)->patchJson("/api/ideas/{$idea->id}", [
            'title' => $idea->title,
            'description' => $idea->description,
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('print.png'),
        ]);

        $path = IdeaAttachment::first()->path;

        $response = $this->actingAs($author)->patchJson("/api/ideas/{$idea->id}", [
            'title' => $idea->title,
            'description' => $idea->description,
            'type' => IdeaType::PROBLEM->value,
            'removeAttachment' => true,
        ]);

        $response->assertOk();
        expect($response->json('data.attachment'))->toBeNull();
        expect(IdeaAttachment::count())->toBe(0);
        Storage::disk('public')->assertMissing($path);
    });

    it('leaves no orphan file when the idea is withdrawn', function () {
        $author = ideaContributor();

        $created = $this->actingAs($author)->postJson('/api/ideas', [
            'title' => 'Some junto',
            'description' => 'Descrição',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('print.png'),
        ]);

        $path = IdeaAttachment::first()->path;

        $this->actingAs($author)->deleteJson('/api/ideas/'.$created->json('data.id'))->assertOk();

        expect(IdeaAttachment::count())->toBe(0);
        Storage::disk('public')->assertMissing($path);
    });

    it('writes to whichever disk the configuration names', function () {
        Storage::fake('local');
        config(['ideas.attachment_disk' => 'local']);

        $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'Título',
            'description' => 'Descrição',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('print.png'),
        ])->assertStatus(201);

        expect(IdeaAttachment::first()->disk)->toBe('local');
        Storage::disk('local')->assertExists(IdeaAttachment::first()->path);
    });

    /*
     * The URL is built from the DISK's configured url, which Laravel derives from APP_URL. That
     * makes an attachment's reachability a deployment concern, and a silent one: a wrong APP_URL
     * serves a perfectly stored file behind a link that 404s, and the only symptom is a broken
     * image. Verified for real against the local Valet host, where APP_URL was still Laravel's
     * `http://localhost` default and every attachment link was dead.
     *
     * This pins the contract so the dependency is at least written down in a test.
     */
    it('builds the url from the configured disk url, not from the request host', function () {
        /*
         * The url is handed to `Storage::fake` explicitly, because `fake()` merges only the config
         * passed to IT with a temp root — it never reads the disk's configured `url`. That is why
         * a faked disk answers with a RELATIVE path, and why every other test in this file
         * asserting `toContain('ideas/')` could never have caught an absolute-url regression.
         */
        Storage::fake('public', ['url' => 'https://api.example.test/storage']);

        $response = $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'With an image',
            'description' => 'Descrição',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('print.png'),
        ]);

        expect($response->json('data.attachment.url'))->toStartWith('https://api.example.test/storage/ideas/');
    });
});
