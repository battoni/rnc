<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class LoginCodeVerifyDTO extends Data
{
    public function __construct(
        public string $phone,
        public string $code,
    ) {}
}
