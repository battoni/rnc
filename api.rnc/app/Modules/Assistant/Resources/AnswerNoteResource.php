<?php

namespace App\Modules\Assistant\Resources;

use App\Modules\Assistant\Models\AssistantNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A served note as the assistant answers with it (rnc-3 R12): the text, "Regra: <rule>", "Fonte:
 * <source>, lida em <as_of>" and "revisado por <name> em <date>" — the SPA writes the sentences.
 * No keywords, no status, no editor: what the person asking reads.
 *
 * @mixin AssistantNote
 */
class AnswerNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'question' => $this->question,
            'answer' => $this->answer,
            'rule' => $this->rule,
            'source_label' => $this->source_label,
            'source_url' => $this->source_url,
            'as_of' => $this->as_of->toDateString(),
            'reviewer' => AssistantPersonResource::of($this->reviewer),
            'reviewed_at' => $this->reviewed_at?->utc()->toIso8601String(),
        ];
    }
}
