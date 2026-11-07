<?php

namespace App\Modules\Firm\Resources;

use App\Modules\Firm\Models\Firm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The console's first row, "Marca principal" (rnc-3 R5): the firm's own look, read-only here, and
 * the leads started under no sub-brand. Built from `['firm' => Firm|null, 'lead_count' => int]`.
 */
class MainBrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Firm|null $firm */
        $firm = $this->resource['firm'];

        return [
            'name' => $firm?->name,
            'brand_primary' => $firm?->brand_primary,
            'brand_accent' => $firm?->brand_accent,
            'lead_count' => $this->resource['lead_count'],
        ];
    }
}
