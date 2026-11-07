<?php

namespace App\Modules\Idea\Resources;

use App\Modules\Idea\Models\NewsRead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One person who read an entry, and when they first did.
 *
 * Deliberately thin: a name and a moment. This list answers "did anyone read what I published",
 * and widening it into a user profile would turn a writer's feedback loop into a directory of
 * everybody's reading habits.
 *
 * @mixin NewsRead
 */
class NewsReaderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'readAt' => $this->read_at->toIso8601String(),
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => trim($this->user->name.' '.($this->user->last_name ?? '')),
            ] : null,
        ];
    }
}
