<?php

namespace App\Modules\Tip\DTOs;

use Spatie\LaravelData\Data;

class TipStoreDTO extends Data
{
    public function __construct(
        public string $anchor_key,
        public string $content,
        public ?string $change_reason = null,
    ) {}
}
