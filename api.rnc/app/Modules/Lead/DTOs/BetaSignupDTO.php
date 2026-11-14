<?php

namespace App\Modules\Lead\DTOs;

use App\Modules\Tracking\Enums\TrafficSource;
use Spatie\LaravelData\Data;

/**
 * A firm's beta signup (rnc-4 R29), validated and normalised: the contact goes to the lead's
 * columns, the rest of the form to its `profile` (D29).
 */
class BetaSignupDTO extends Data
{
    public function __construct(
        public string $company,
        public string $name,
        public string $role,
        public string $email,
        public string $phone,
        public string $city,
        public string $uf,
        public string $clients_band,
        public bool $consent,
        public ?string $message = null,
        public string $source = TrafficSource::Direct->value,
        public ?string $medium = null,
    ) {}

    /** @return array{role: string, city: string, uf: string, clients_band: string, message: string|null} */
    public function profile(): array
    {
        return ['role' => $this->role, 'city' => $this->city, 'uf' => $this->uf, 'clients_band' => $this->clients_band, 'message' => $this->message];
    }
}
