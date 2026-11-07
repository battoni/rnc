<?php

namespace App\Modules\Firm\Models;

use Database\Factories\FirmFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant: the accounting firm whose hub this is. Franco Contabilidade is firm 1; the RNC firms and
 * the Express/Prime sub-brands of Phase 3 are more rows of this table, not more deploys.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $logo_text
 * @property string $brand_primary
 * @property string $brand_accent
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $mailbox
 * @property list<array{weekdays: list<int>, from: string, to: string}> $business_hours
 */
#[Fillable(['name', 'slug', 'logo_text', 'brand_primary', 'brand_accent', 'address', 'phone', 'mailbox', 'business_hours'])]
class Firm extends Model
{
    /** @use HasFactory<FirmFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
        ];
    }

    protected static function newFactory(): FirmFactory
    {
        return FirmFactory::new();
    }
}
