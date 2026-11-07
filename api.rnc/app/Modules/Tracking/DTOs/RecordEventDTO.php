<?php

namespace App\Modules\Tracking\DTOs;

use Spatie\LaravelData\Data;

class RecordEventDTO extends Data
{
    public function __construct(
        public string $visitor,
        public string $type,
        public string $path,
        public string $source,
        public ?string $sector = null,
        public ?string $medium = null,
        public ?string $lead_token = null,
    ) {}
}
