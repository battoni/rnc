<?php

namespace App\Modules\Content\Resources;

use App\Modules\Content\Models\SectorPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sector page as the landing's grid lists it. An allow-list (`arcus-api-architecture`, "A route
 * without auth answers through a `Public*` resource"): no id, no firm.
 *
 * @mixin SectorPage
 */
class PublicSectorPageSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'label' => $this->label,
            'title' => $this->title,
            'lede' => $this->lede,
        ];
    }
}
