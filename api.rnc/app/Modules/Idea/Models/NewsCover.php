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
    'news_id',
    'disk',
    'path',
    'mime',
    'size',
])]
class NewsCover extends Model
{
    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    /** @return BelongsTo<News, $this> */
    public function news(): BelongsTo
    {
        return $this->belongsTo(News::class);
    }

    /** Resolved from the row's OWN disk, never from config — see the migration's note. */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
