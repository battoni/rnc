<?php

namespace App\Modules\Auth\DTOs;

use Spatie\LaravelData\Data;

class RegisterDTO extends Data
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $password,
        public string $cpf,
        public string $birthDate,
        public ?string $phone = null,
        public ?string $profileType = null,
    ) {}
}
