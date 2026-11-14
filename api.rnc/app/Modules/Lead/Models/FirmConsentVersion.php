<?php

namespace App\Modules\Lead\Models;

use App\Modules\Firm\Traits\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

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
}
