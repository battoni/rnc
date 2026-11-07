<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class LoginCodeRequestDTO extends Data
{
    public function __construct(
        public string $phone,
    ) {}
}
