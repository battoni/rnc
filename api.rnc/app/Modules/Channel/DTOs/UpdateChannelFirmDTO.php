<?php

namespace App\Modules\Channel\DTOs;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/**
 * A channel firm's edit (`PATCH /api/channel/firms/{id}`): only what the request sent. An explicit
 * null clears the notes. The stage is not here — it moves only through the advance.
 */
class UpdateChannelFirmDTO extends Data
{
    public function __construct(
        public string|Optional $name,
        public string|Optional $city,
        public int|Optional $monthly_fee_cents,
        public int|Optional $share_bp,
        public string|null|Optional $notes,
    ) {}
}
