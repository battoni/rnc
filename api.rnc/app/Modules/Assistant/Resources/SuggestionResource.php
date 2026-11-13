<?php

namespace App\Modules\Assistant\Resources;

use App\Modules\Assistant\Models\AssistantNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A suggestion chip (rnc-3 R12): a served note's id and question. Clicking it asks by the id (D9).
 *
 * @mixin AssistantNote
 */
class SuggestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'question' => $this->question];
    }
}
