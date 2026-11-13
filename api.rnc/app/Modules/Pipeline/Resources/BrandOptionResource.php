<?php

namespace App\Modules\Pipeline\Resources;

use App\Modules\Firm\Models\FirmBrand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One option of the board's "Marca" filter (rnc-3 F2, R4): the slug the filter sends back, the short
 * name the chip already shows, and whether the brand is still live. Nothing an edit form needs.
 *
 * @mixin FirmBrand
 */
class BrandOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'short_name' => $this->short_name,
            'active' => $this->active,
        ];
    }
}
