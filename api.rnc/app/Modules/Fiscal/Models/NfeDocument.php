<?php

namespace App\Modules\Fiscal\Models;

use App\Modules\Booking\Casts\UtcDateTime;
use App\Modules\Firm\Traits\BelongsToFirm;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One NF-e as one client sees it (rnc-2 R16, D7). Unique per firm + key + client (spec C1).
 *
 * @property int $id
 * @property int $firm_id
 * @property int $client_id Portfolio's `clients.id`
 * @property int $import_id
 * @property string $direction `NfeDirection`
 * @property string $access_key
 * @property int $number
 * @property int $series
 * @property CarbonImmutable $issued_at UTC
 * @property string $emitter_cnpj
 * @property int|null $emitter_crt
 * @property string|null $recipient_cnpj
 * @property string $recipient_kind `NfeRecipientKind`
 * @property int $total_cents
 * @property string|null $system `ide/verProc`
 * @property int $items
 * @property int $items_missing_group
 * @property int $items_missing_codes
 * @property int $items_without_test_values
 * @property array{missing_group: list<int>, missing_codes: list<int>, without_test_values: list<int>} $sample
 * @property string $path on the private `local` disk
 */
#[Fillable([
    'firm_id', 'client_id', 'import_id', 'direction', 'access_key', 'number', 'series', 'issued_at',
    'emitter_cnpj', 'emitter_crt', 'recipient_cnpj', 'recipient_kind', 'total_cents', 'system', 'items',
    'items_missing_group', 'items_missing_codes', 'items_without_test_values', 'sample', 'path',
])]
class NfeDocument extends Model
{
    use BelongsToFirm;

    protected function casts(): array
    {
        return [
            'client_id' => 'integer',
            'import_id' => 'integer',
            'number' => 'integer',
            'series' => 'integer',
            'issued_at' => UtcDateTime::class,
            'emitter_crt' => 'integer',
            'total_cents' => 'integer',
            'items' => 'integer',
            'items_missing_group' => 'integer',
            'items_missing_codes' => 'integer',
            'items_without_test_values' => 'integer',
            'sample' => 'array',
        ];
    }
}
