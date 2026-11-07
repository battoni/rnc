<?php

namespace App\Modules\Idea\DTOs;

use Spatie\LaravelData\Data;

class IdeaCommentStoreDTO extends Data
{
    public function __construct(
        public string $body,
    ) {}
}
