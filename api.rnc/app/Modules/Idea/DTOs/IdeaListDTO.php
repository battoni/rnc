<?php

namespace App\Modules\Idea\DTOs;

use Spatie\LaravelData\Data;

class IdeaListDTO extends Data
{
    /**
     * Every filter is optional: the board's first job is to show the board. `mine` narrows to
     * the viewer's own ideas, which is the one question the author asks that a status filter
     * cannot answer.
     *
     * @param  string|null  $sort  'votes' (default) or 'recent'
     */
    public function __construct(
        public ?string $status,
        public ?string $type,
        public ?string $area,
        public ?string $search,
        public ?bool $mine,
        public ?string $sort,
    ) {}
}
