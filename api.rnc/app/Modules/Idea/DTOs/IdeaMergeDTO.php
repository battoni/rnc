<?php

namespace App\Modules\Idea\DTOs;

use Spatie\LaravelData\Data;

class IdeaMergeDTO extends Data
{
    public function __construct(
        public int $targetId,
    ) {}
}
