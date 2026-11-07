<?php

namespace App\Modules\Partner\Resources;

use App\Modules\Partner\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Who is signed in to the portal (`GET /api/portal/me`, rnc-2 R3): the partner and the firm whose
 * portal it is. An allow-list — the partner's registry fields (e-mail, phone) stay with Franco.
 *
 * @mixin Partner
 */
class PortalMeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'partner' => ['id' => $this->id, 'name' => $this->name, 'specialty' => $this->specialty],
            'firm' => ['name' => $this->firm->name, 'slug' => $this->firm->slug],
        ];
    }
}
