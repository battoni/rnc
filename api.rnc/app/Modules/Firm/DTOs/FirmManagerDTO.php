<?php

namespace App\Modules\Firm\DTOs;

use Spatie\LaravelData\Data;

/**
 * The console's "manager login" (rnc-4 R25): the username of the firm's manager to reset, or a new
 * one to create, and the new account's e-mail. `FirmProvisioner::provideManager` decides which.
 */
class FirmManagerDTO extends Data
{
    public function __construct(
        public string $username,
        public ?string $email = null,
    ) {}
}
