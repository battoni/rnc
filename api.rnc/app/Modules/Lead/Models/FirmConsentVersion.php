<?php

namespace App\Modules\Lead\Models;

use App\Models\User;
use App\Modules\Firm\Traits\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One version of a firm's partner-introduction consent (rnc-4 R13, D12). Never edited: a new text is
 * a new row, version n+1. `text` may hold `{escritorio}` (`ConsentTerms::PLACEHOLDER`). Written only
 * by `ConsentTextService`.
 *
 * @property int $id
 * @property int $firm_id
 * @property int $version
 * @property string $text
 * @property int|null $author_user_id
 * @property Carbon $created_at
 * @property-read User|null $author who wrote it (rnc-4 F5a); null for a seeded or copied version
 */
#[Fillable(['firm_id', 'version', 'text', 'author_user_id'])]
class FirmConsentVersion extends Model
{
    use BelongsToFirm;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'author_user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
