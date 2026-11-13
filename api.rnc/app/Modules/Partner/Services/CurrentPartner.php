<?php

namespace App\Modules\Partner\Services;

use App\Modules\Partner\Models\Partner;

/**
 * The partner a portal request belongs to (rnc-2 R4, D2) — the second scope after `CurrentFirm`.
 *
 * A container binding (scoped, so it never leaks from one request to the next), set only by
 * `ResolveCurrentPartner` from the signed-in user's `partner_id`, on the `portal/*` routes. Portal
 * repositories filter by it and never by an id the client sent.
 */
class CurrentPartner
{
    private ?Partner $partner = null;

    public function get(): ?Partner
    {
        return $this->partner;
    }

    public function id(): ?int
    {
        return $this->partner?->id;
    }

    public function set(?Partner $partner): void
    {
        $this->partner = $partner;
    }
}
