<?php

namespace App\Modules\Channel\DTOs;

use Spatie\LaravelData\Data;

/** A new channel firm (`POST /api/channel/firms`). A fee or a share left out takes `config/channel.php`'s. */
class ChannelFirmDTO extends Data
{
    public function __construct(
        public string $name,
        public string $city,
        public ?int $monthly_fee_cents = null,
        public ?int $share_bp = null,
        public ?string $notes = null,
    ) {}
}
