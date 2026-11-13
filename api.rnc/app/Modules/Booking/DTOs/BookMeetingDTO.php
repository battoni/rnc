<?php

namespace App\Modules\Booking\DTOs;

use Spatie\LaravelData\Data;

class BookMeetingDTO extends Data
{
    public function __construct(
        public int $specialist_id,
        public string $starts_at,
        public ?string $email = null,
    ) {}
}
