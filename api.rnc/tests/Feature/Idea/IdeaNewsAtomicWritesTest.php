<?php

use App\Modules\Idea\Enums\IdeaType;
use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaAttachment;
use App\Modules\Idea\Models\News;
use App\Modules\Idea\Models\NewsArea;
use App\Modules\Idea\Models\NewsCover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Review of 2026-09-17 (ARC-11): an idea and its image, and a Novidades entry with its areas and
 * cover, were written as separate statements with no transaction. A failure in the middle left
 * an idea without the image the person attached, an entry whose areas had been deleted and never
 * rewritten, and a blob on disk no row pointed at — while the API answered 500 after writing.
 * Each failure here is injected on the SECOND write of the gesture.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

function atomicWritesFailOn(string $model): void
{
    $model::creating(function (): void {
        throw new RuntimeException("{$model} insert failed");
    });
}

describe('an idea and its image are one write', function () {
    it('keeps neither the idea nor the file when the attachment row fails', function () {
        atomicWritesFailOn(IdeaAttachment::class);

        $this->actingAs(ideaContributor())->postJson('/api/ideas', [
            'title' => 'Com anexo',
            'description' => 'Print em anexo.',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('print.png'),
        ])->assertServerError();

        expect(Idea::count())->toBe(0);
        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('keeps the old text and the old image when replacing the attachment fails', function () {
        $author = ideaContributor();

        $ideaId = $this->actingAs($author)->postJson('/api/ideas', [
            'title' => 'Original',
            'description' => 'Print em anexo.',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('first.png'),
        ])->assertCreated()->json('data.id');

        $oldPath = IdeaAttachment::sole()->path;

        atomicWritesFailOn(IdeaAttachment::class);

        $this->actingAs($author)->patchJson("/api/ideas/{$ideaId}", [
            'title' => 'Reescrito',
            'description' => 'Outro print.',
            'type' => IdeaType::PROBLEM->value,
            'attachment' => UploadedFile::fake()->image('second.png'),
        ])->assertServerError();

        expect(Idea::find($ideaId)->title)->toBe('Original');
        expect(IdeaAttachment::sole()->path)->toBe($oldPath);
        expect(Storage::disk('public')->allFiles())->toBe([$oldPath]);
    });
});

describe('a Novidades entry, its areas and its cover are one write', function () {
    it('keeps no entry, no areas and no file when the cover row fails', function () {
        atomicWritesFailOn(NewsCover::class);

        $this->actingAs(newsWriter())->postJson('/api/news', [
            'title' => 'Com capa',
            'body' => '<p>Corpo.</p>',
            'areas' => ['home'],
            'cover' => UploadedFile::fake()->image('cover.png'),
        ])->assertServerError();

        expect(News::count())->toBe(0);
        expect(NewsArea::count())->toBe(0);
        expect(Storage::disk('public')->allFiles())->toBe([]);
    });

    it('keeps the old text and areas when rewriting the areas fails', function () {
        $writer = newsWriter();

        $newsId = $this->actingAs($writer)->postJson('/api/news', [
            'title' => 'Original',
            'body' => '<p>Corpo.</p>',
            'areas' => ['home'],
        ])->assertCreated()->json('data.id');

        atomicWritesFailOn(NewsArea::class);

        $this->actingAs($writer)->patchJson("/api/news/{$newsId}", [
            'title' => 'Reescrito',
            'body' => '<p>Outro corpo.</p>',
            'areas' => ['account'],
            'publish' => false,
        ])->assertServerError();

        expect(News::find($newsId)->title)->toBe('Original');
        expect(NewsArea::toBase()->pluck('area')->all())->toBe(['home']);
    });
});
