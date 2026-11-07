<?php

namespace App\Modules\Idea\DTOs;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class IdeaUpdateDTO extends Data
{
    /**
     * `removeAttachment` is separate from `attachment` because "send no file" and "delete the
     * file I sent" are different intents that a multipart request cannot otherwise tell apart —
     * an absent field is how every other unchanged field arrives.
     */
    public function __construct(
        public string $title,
        public string $description,
        public string $type,
        public ?string $area,
        public ?UploadedFile $attachment,
        public ?bool $removeAttachment,
    ) {}
}
