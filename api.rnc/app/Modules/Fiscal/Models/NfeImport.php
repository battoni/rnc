<?php

namespace App\Modules\Fiscal\Models;

use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One NF-e import batch (rnc-2 R17). Written only by `NfeImporter`.
 *
 * @property int $id
 * @property int $firm_id
 * @property int|null $user_id
 * @property string $source `NfeImportSource`
 * @property int $files
 * @property int $stored
 * @property list<array{file: string, code: string}> $refused
 * @property CarbonImmutable $at UTC
 * @property string|null $by_name who imported, only on a row read by `NfeImportRepository`'s list query
 */
#[Fillable(['firm_id', 'user_id', 'source', 'files', 'stored', 'refused', 'at'])]
class NfeImport extends Model
{
    use BelongsToFirm;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'files' => 'integer',
            'stored' => 'integer',
            'refused' => 'array',
            'at' => UtcDateTime::class,
        ];
    }
}
