<?php

namespace App\Modules\Partner\Resources;

use App\Modules\Partner\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One option of "Encaminhar ao jurídico" (rnc-3 R20): the partner's id, name and specialty — no
 * contact, the picker only names whom the contract goes to.
 *
 * @mixin Partner
 */
class LegalPartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'specialty' => $this->specialty,
        ];
    }
}
