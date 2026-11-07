<?php

namespace App\Modules\Idea\Resources;

use App\Modules\Idea\Models\Idea;
use App\Modules\Idea\Services\IdeaAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Idea
 */
class IdeaResource extends JsonResource
{
    /**
     * `author` is null for a removed account and the client renders that as a removed account —
     * the row outlives the login, which is why `user_id` is nullOnDelete.
     *
     * `voted` and `canEdit` are viewer-relative and travel WITH the row rather than being
     * recomputed on the client. `voted` rides on the board query as a computed column; `canEdit`
     * comes from the model, which is the single definition the write endpoint also enforces.
     *
     * `canModerate` is asked per row but costs one pair of queries per REQUEST: PermissionService
     * memoises on the request object.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $canModerate = $viewer ? app(IdeaAccessService::class)->canModerate($viewer) : false;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'area' => $this->area?->value,
            'status' => $this->status->value,
            'adminNote' => $this->admin_note,
            'votesCount' => $this->votes_count,
            'commentsCount' => $this->comments_count,
            'voted' => (bool) ($this->voted ?? false),
            'canEdit' => $viewer ? $this->resource->isEditableBy($viewer->id, $canModerate) : false,
            'hidden' => $this->hidden_at !== null,
            'mergedIntoId' => $this->merged_into_id ? (int) $this->merged_into_id : null,
            'deliveredAt' => $this->delivered_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'author' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'attachment' => $this->whenLoaded('attachment', fn () => $this->attachment ? [
                'url' => $this->attachment->url(),
                'mime' => $this->attachment->mime,
                'size' => $this->attachment->size,
            ] : null),
            'comments' => IdeaCommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
