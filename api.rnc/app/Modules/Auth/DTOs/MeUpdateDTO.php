<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class MeUpdateDTO extends Data
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $last_name,
    ) {}
}
