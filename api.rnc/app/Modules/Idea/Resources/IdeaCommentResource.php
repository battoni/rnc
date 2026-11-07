<?php

namespace App\Modules\Idea\Resources;

use App\Modules\Idea\Models\IdeaComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin IdeaComment
 */
class IdeaCommentResource extends JsonResource
{
    /**
     * `isOfficial` is the stored flag, not a role read at render time — someone who answered as
     * the maintainer keeps having answered as the maintainer.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ideaId' => (int) $this->idea_id,
            'body' => $this->body,
            'isOfficial' => $this->is_official,
            'hidden' => $this->hidden_at !== null,
            /*
             * The two moderator gestures return the same shape, and `hidden` alone cannot tell
             * them apart: a comment removed for good was already hidden, so it comes back with
             * `hidden: true` and the client would fold it back into the thread instead of
             * dropping it. `exists` is false on a model Eloquent has just deleted, which is the
             * one honest signal available.
             */
            'deleted' => ! $this->resource->exists,
            'createdAt' => $this->created_at?->toIso8601String(),
            'author' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
        ];
    }
}
