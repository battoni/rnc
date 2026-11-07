<?php

namespace App\Modules\Tip\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tip_id', 'content', 'event', 'restored_from_id', 'changed_by', 'change_reason'])]
class TipHistory extends Model
{
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function restoredFrom(): BelongsTo
    {
        return $this->belongsTo(TipHistory::class, 'restored_from_id');
    }
}
