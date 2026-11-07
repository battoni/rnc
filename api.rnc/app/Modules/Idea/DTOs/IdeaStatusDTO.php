<?php

namespace App\Modules\Idea\DTOs;

use Spatie\LaravelData\Data;

class IdeaStatusDTO extends Data
{
    /**
     * The note travels with the status because they are one gesture. A status changed without a
     * sentence is the board going quiet in a new colour.
     */
    public function __construct(
        public string $status,
        public ?string $adminNote,
    ) {}
}
