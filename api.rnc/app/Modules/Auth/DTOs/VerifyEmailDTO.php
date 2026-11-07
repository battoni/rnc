<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class VerifyEmailDTO extends Data
{
    public function __construct(
        public string $email,
        public string $code,
    ) {}
}
