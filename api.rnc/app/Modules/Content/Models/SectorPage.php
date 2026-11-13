<?php

namespace App\Modules\Content\Models;

use App\Modules\Firm\Traits\BelongsToFirm;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A sector page of the public site (rnc-1 R6): "A Reforma para o comércio" and its five siblings.
 * Firm-scoped, so each firm writes its own pages.
 *
 * @property int $id
 * @property int $firm_id
 * @property string $slug
 * @property string $label
 * @property string $title
 * @property string $lede
 * @property list<array{date: string, text: string, source: array{label: string, url: string|null}}> $facts
 * @property array{title: string, description: string} $seo
 * @property int $position
 */
#[Fillable(['firm_id', 'slug', 'label', 'title', 'lede', 'facts', 'seo', 'position'])]
class SectorPage extends Model
{
    use BelongsToFirm;

    protected function casts(): array
    {
        return [
            'facts' => 'array',
            'seo' => 'array',
            'position' => 'integer',
        ];
    }
}
