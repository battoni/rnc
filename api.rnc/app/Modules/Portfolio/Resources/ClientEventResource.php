<?php

namespace App\Modules\Portfolio\Resources;

use App\Modules\Portfolio\Models\ClientEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of a client's timeline (rnc-2 R20, R22): what (`type` + its data), when (UTC, ISO-8601
 * with offset) and who — null when no person did it. The SPA writes the sentence.
 *
 * @mixin ClientEvent
 */
class ClientEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'client_id' => (int) $this->client_id,
            'type' => $this->type,
            'data' => $this->data ?: (object) [],
            'at' => $this->at->toIso8601String(),
            'user' => $this->user ? ['id' => (int) $this->user->id, 'name' => trim($this->user->name.' '.$this->user->last_name)] : null,
        ];
    }
}
