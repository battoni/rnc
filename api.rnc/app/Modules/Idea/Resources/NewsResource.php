<?php

namespace App\Modules\Idea\Resources;

use App\Modules\Idea\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin News
 */
class NewsResource extends JsonResource
{
    /**
     * `idea` is the credit line: when an entry answers someone's idea, Novidades names them.
     * Most entries answer none, and the field is simply null — the changelog reports the whole
     * pace of the product, not only the part the board asked for.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            /*
             * HTML, and safe to render — it was sanitised on the way in, so this is not a promise
             * the client has to keep. See NewsBodySanitizer.
             */
            'body' => $this->body,
            'link' => $this->link,
            'cover' => $this->whenLoaded('cover', fn () => $this->cover ? [
                'url' => $this->cover->url(),
                'mime' => $this->cover->mime,
                'size' => $this->cover->size,
            ] : null),
            'areas' => $this->whenLoaded('areas', fn () => $this->areas->pluck('area')->map(fn ($area) => $area->value)->all(), []),
            'published' => (bool) $this->is_published,
            /*
             * When it first went out, kept even while it is pulled back to a draft — an entry that
             * returns keeps its place in the changelog instead of jumping to today.
             */
            'publishedAt' => $this->published_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            /*
             * Applause. `reactions` is the room, `myReactions` is this reader's own share of it —
             * the client needs both to show a total and still highlight what you personally spent.
             * Present only when the aggregates were attached; a bare model returns neither rather
             * than lying with zeroes.
             */
            'reactions' => $this->when(isset($this->reaction_totals), fn () => $this->reaction_totals),
            'myReactions' => $this->when(isset($this->reaction_mine), fn () => $this->reaction_mine),
            'read' => $this->when(isset($this->is_read_by_user), fn () => (bool) $this->is_read_by_user),
            /*
             * How many people read it — the writer's number. Absent for a reader, and absent by
             * NOT BEING SET rather than by being checked here: the service decides who gets it,
             * and a resource that re-derives a permission is a second place for that rule to be
             * wrong.
             */
            'readCount' => $this->when(isset($this->read_count), fn () => (int) $this->read_count),
            'idea' => $this->whenLoaded('idea', fn () => $this->idea ? [
                'id' => $this->idea->id,
                'title' => $this->idea->title,
                'author' => $this->idea->user ? [
                    'id' => $this->idea->user->id,
                    'name' => $this->idea->user->name,
                ] : null,
            ] : null),
        ];
    }
}
