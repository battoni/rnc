<?php

namespace App\Modules\Idea\Services;

use App\Modules\Idea\Models\News;
use App\Modules\Idea\Models\NewsCover;
use Illuminate\Http\UploadedFile;

/**
 * The image at the top of a Novidades entry. A thin wrapper: every rule about where the file goes
 * and what it may be called lives in `ImageAttachmentService`, in one copy.
 */
class NewsCoverService
{
    private const DIRECTORY = 'news';

    public function __construct(
        private ImageAttachmentService $images,
    ) {}

    public function replaceCover(News $news, UploadedFile $file): NewsCover
    {
        return $this->images->replace($news->cover(), 'cover', self::DIRECTORY, $file);
    }

    public function deleteCover(News $news): void
    {
        $this->images->delete($news, 'cover');
    }
}
