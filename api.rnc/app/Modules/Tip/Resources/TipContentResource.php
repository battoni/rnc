<?php

namespace App\Modules\Tip\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What every authenticated user gets in order to render tips: the anchor and the text, nothing
 * else. Who wrote a tip and when is management information, and it would otherwise ride along in
 * a payload fetched on every session by everyone.
 */
class TipContentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'anchor_key' => $this->resource->anchor_key,
            'content' => $this->resource->content,
        ];
    }
}
