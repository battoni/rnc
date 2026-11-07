<?php

namespace App\Modules\Tip\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class TipUpdateDTO extends Data
{
    public function __construct(
        public string|Optional $anchor_key,
        public string|Optional $content,
        public Optional|null|string $change_reason,
    ) {}
}
