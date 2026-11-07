<?php

namespace App\Modules\Tip\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tip = $this->resource;

        return [
            'id' => $tip->id,
            'anchor_key' => $tip->anchor_key,
            'content' => $tip->content,
            'archived_at' => $tip->archived_at,
            'created_by' => $tip->relationLoaded('createdBy') && $tip->createdBy
                ? ['id' => $tip->createdBy->id, 'name' => $tip->createdBy->name]
                : null,
            'updated_by' => $tip->relationLoaded('updatedBy') && $tip->updatedBy
                ? ['id' => $tip->updatedBy->id, 'name' => $tip->updatedBy->name]
                : null,
            'created_at' => $tip->created_at,
            'updated_at' => $tip->updated_at,
        ];
    }
}
