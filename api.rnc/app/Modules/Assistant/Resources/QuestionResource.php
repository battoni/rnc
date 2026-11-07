<?php

namespace App\Modules\Assistant\Resources;

use App\Modules\Assistant\Models\AssistantQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A handed-off question (rnc-3 R13): the words, its status and the specialist who holds it.
 *
 * @mixin AssistantQuestion
 */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question' => $this->question,
            'status' => $this->status,
            'specialist' => AssistantPersonResource::of($this->specialist),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
