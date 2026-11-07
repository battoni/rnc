<?php

namespace App\Modules\Tip\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['anchor_key', 'content', 'archived_at', 'created_by', 'updated_by'])]
class Tip extends Model
{
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TipHistory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
