<?php

namespace App\Modules\Assistant\Resources;

use App\Models\User;
use App\Modules\Assistant\Models\AssistantNote;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The assistant's answer (rnc-3 R12, R13): the question as asked, the served note or null, and — only
 * with no note — the firm's active specialists by name, the hand-off's picker. Nothing else: with no
 * note there is no text of the API's own (W50); the SPA shows its fixed sentence.
 *
 * @property array{question: string, note: AssistantNote|null, specialists: Collection<int, User>} $resource
 */
class AnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $note = $this->resource['note'];

        return [
            'question' => $this->resource['question'],
            'note' => $note === null ? null : (new AnswerNoteResource($note))->toArray($request),
            'specialists' => AssistantPersonResource::collection($this->resource['specialists'])->toArray($request),
        ];
    }
}
