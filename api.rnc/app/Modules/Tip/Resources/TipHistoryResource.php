<?php

namespace App\Modules\Tip\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TipHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $history = $this->resource;

        return [
            'id' => $history->id,
            'content' => $history->content,
            'event' => $history->event,
            /*
             * The id of the version this one was restored from, not a rendered sentence: the
             * frontend builds "restored the version from 12/03" in the reader's language.
             */
            'restored_from_id' => $history->restored_from_id,
            'changed_by' => $history->changedBy
                ? ['id' => $history->changedBy->id, 'name' => $history->changedBy->name]
                : null,
            'change_reason' => $history->change_reason,
            'created_at' => $history->created_at,
        ];
    }
}
