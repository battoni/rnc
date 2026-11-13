<?php

namespace App\Modules\Partner\Repositories;

use App\Modules\Partner\Models\PartnerAccessEvent;

/** The grant/revoke history (plan AM9). `BelongsToFirm` stamps and scopes the firm. */
class PartnerAccessEventRepository
{
    /** @param array{firm_id: int, partner_id: int, user_id: int, type: string, at: mixed} $attributes */
    public function createPartnerAccessEvent(array $attributes): PartnerAccessEvent
    {
        return PartnerAccessEvent::query()->create($attributes);
    }
}
