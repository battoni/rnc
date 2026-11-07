<?php

namespace App\Modules\Idea\Services;

use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Models\IdeaAttachment;
use Illuminate\Http\UploadedFile;

/**
 * The screenshot on a bug report. A thin wrapper: every rule about where the file goes and what it
 * may be called lives in `ImageAttachmentService`, in one copy, shared with the Novidades cover.
 */
class IdeaAttachmentService
{
    private const DIRECTORY = 'ideas';

    /*
     * One image per idea, and there is no constant for it because nothing reads one.
     *
     * The limit is not enforced by a check: `ImageAttachmentService::replace` deletes the existing
     * row before writing the new one, so "one" is what the write path does rather than what a
     * guard permits. A `MAX_PER_IDEA = 1` sat here for a while claiming to enforce it, read by
     * nobody. Raising the limit means changing `replace`, not this docblock.
     */

    public function __construct(
        private ImageAttachmentService $images,
    ) {}

    public function replaceAttachment(Idea $idea, UploadedFile $file): IdeaAttachment
    {
        return $this->images->replace($idea->attachment(), 'attachment', self::DIRECTORY, $file);
    }

    public function deleteAttachment(Idea $idea): void
    {
        $this->images->delete($idea, 'attachment');
    }
}
