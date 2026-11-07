<?php

namespace App\Modules\Idea\DTOs;

use Illuminate\Http\UploadedFile;
use Spatie\LaravelData\Data;

class IdeaStoreDTO extends Data
{
    public function __construct(
        public string $title,
        public string $description,
        public string $type,
        public ?string $area,
        public ?UploadedFile $attachment,
    ) {}
}
