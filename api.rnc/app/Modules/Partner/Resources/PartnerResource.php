<?php

namespace App\Modules\Partner\Resources;

use App\Modules\Partner\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A partner of the registry (R21), for the people who manage it.
 *
 * @mixin Partner
 */
class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'specialty' => $this->specialty,
            'email' => $this->email,
            'phone' => $this->phone,
            'city' => $this->city,
            'active' => $this->active,
        ];
    }
}
