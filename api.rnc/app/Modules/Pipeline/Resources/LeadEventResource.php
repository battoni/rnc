<?php

namespace App\Modules\Pipeline\Resources;

use App\Modules\Lead\Models\LeadEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of a lead's timeline (R19): what happened (`type` + its data), when, and who — null for
 * the visitor. The SPA writes the sentence.
 *
 * @mixin LeadEvent
 */
class LeadEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $this->data ?? (object) [],
            'at' => $this->at->toIso8601String(),
            'user' => $this->user ? ['id' => $this->user->id, 'name' => trim($this->user->name.' '.$this->user->last_name)] : null,
        ];
    }
}
