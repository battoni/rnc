<?php

namespace App\Modules\Idea\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $size
 */
#[Fillable([
    'idea_id',
    'disk',
    'path',
    'mime',
    'size',
])]
class IdeaAttachment extends Model
{
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<Idea, $this> */
    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    /**
     * Resolved from the row's OWN disk, never from config. The disk is configurable so that it
     * can change; a row written before that change still has to point at the place its file is.
     */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
