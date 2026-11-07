<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class RequestEmailCodeDTO extends Data
{
    public function __construct(
        public string $email,
    ) {}
}
