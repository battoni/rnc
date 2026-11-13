<?php

namespace App\Modules\Firm\Resources;

use App\Modules\Firm\Models\FirmBrand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the sub-brands console (rnc-3 R5): the brand as the operator edits it, and how many
 * leads were started under it. Built from `['brand' => FirmBrand, 'lead_count' => int]`.
 */
class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var FirmBrand $brand */
        $brand = $this->resource['brand'];

        return [
            'id' => $brand->id,
            'slug' => $brand->slug,
            'name' => $brand->name,
            'short_name' => $brand->short_name,
            'tagline' => $brand->tagline,
            'brand_primary' => $brand->brand_primary,
            'brand_accent' => $brand->brand_accent,
            'proof' => $brand->proof,
            'active' => $brand->active,
            'lead_count' => $this->resource['lead_count'],
        ];
    }
}
