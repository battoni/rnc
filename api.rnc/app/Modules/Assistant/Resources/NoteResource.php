<?php

namespace App\Modules\Assistant\Resources;

use App\Models\User;
use App\Modules\Assistant\Enums\NoteStatus;
use App\Modules\Assistant\Models\AssistantNote;
use App\Modules\Assistant\Policies\AssistantNotePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of "Base da Reforma" (rnc-3 R10, R11): the whole note, its stored `status`, the
 * `review_state` the screen shows — rascunho, revisada (served), vencida (revisada, older than 90
 * days: answers nobody) or aposentada — who reviewed and who wrote it, and whether the person
 * reading may review it (a specialist who is not the last editor).
 *
 * @mixin AssistantNote
 */
class NoteResource extends JsonResource
{
    public const STATE_EXPIRED = 'vencida';

    public function toArray(Request $request): array
    {
        /** @var User $viewer */
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'question' => $this->question,
            'answer' => $this->answer,
            'rule' => $this->rule,
            'source_label' => $this->source_label,
            'source_url' => $this->source_url,
            'as_of' => $this->as_of->toDateString(),
            'keywords_strong' => $this->keywords_strong,
            'keywords_weak' => $this->keywords_weak,
            'status' => $this->status,
            'review_state' => $this->reviewState(),
            'reviewer' => AssistantPersonResource::of($this->reviewer),
            'reviewed_at' => $this->reviewed_at?->utc()->toIso8601String(),
            'last_edited_by' => AssistantPersonResource::of($this->lastEditor),
            'can_review' => $this->status !== NoteStatus::Aposentada->value && app(AssistantNotePolicy::class)->review($viewer, $this->resource),
        ];
    }

    private function reviewState(): string
    {
        if ($this->status !== NoteStatus::Revisada->value) {
            return $this->status;
        }

        return $this->resource->isServedAt(CarbonImmutable::now()) ? $this->status : self::STATE_EXPIRED;
    }
}
