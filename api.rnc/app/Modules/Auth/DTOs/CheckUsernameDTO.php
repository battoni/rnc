<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class CheckUsernameDTO extends Data
{
    public function __construct(
        public string $username,
        public ?int $exclude_id = null,
    ) {}
}
