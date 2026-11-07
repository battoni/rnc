<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class LoginCredentialsDTO extends Data
{
    public function __construct(
        public string $username,
        public string $password,
    ) {}
}
