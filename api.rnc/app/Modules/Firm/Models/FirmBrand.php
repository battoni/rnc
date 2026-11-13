<?php

namespace App\Modules\Firm\Models;

use App\Modules\Firm\Traits\BelongsToFirm;
use Database\Factories\FirmBrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A sub-brand of a firm (rnc-3 R1, D1): Franco Express, Franco Prime. It changes what a visitor
 * sees — name, tagline, colours, proof — never who answers: address, phone, mailbox, hours, team
 * and partners stay the firm's. Never deleted, only deactivated, so its leads keep their chip.
 *
 * `firm_id` and `slug` are not fillable: a brand is born in its firm by the seeder and an edit
 * can move neither.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $slug
 * @property string $name
 * @property string $short_name
 * @property string|null $tagline
 * @property string $brand_primary
 * @property string $brand_accent
 * @property list<array{value: string, label: string}> $proof
 * @property bool $active
 */
#[Fillable(['name', 'short_name', 'tagline', 'brand_primary', 'brand_accent', 'proof', 'active'])]
class FirmBrand extends Model
{
    use BelongsToFirm;

    /** @use HasFactory<FirmBrandFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'proof' => 'array',
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): FirmBrandFactory
    {
        return FirmBrandFactory::new();
    }
}
