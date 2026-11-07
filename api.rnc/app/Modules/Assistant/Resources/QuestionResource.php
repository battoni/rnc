<?php

namespace App\Modules\Assistant\Resources;

use App\Modules\Assistant\Models\AssistantQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A handed-off question (rnc-3 R13, R14): the words, its status, who asked, the specialist who holds
 * it, the answer once given and the note "Virar nota" turned it into.
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
            'asked_by' => AssistantPersonResource::of($this->asker),
            'specialist' => AssistantPersonResource::of($this->specialist),
            'answer' => $this->answer,
            'answered_at' => $this->answered_at?->utc()->toIso8601String(),
            'note_id' => $this->note_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
