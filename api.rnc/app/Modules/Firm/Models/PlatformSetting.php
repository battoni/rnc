<?php

namespace App\Modules\Firm\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One setting of the platform itself (rnc-4 D12) — no firm, so no `BelongsToFirm`. Written only by
 * `PlatformSettings`.
 *
 * @property int $id
 * @property string $key
 * @property string $value
 */
#[Fillable(['key', 'value'])]
class PlatformSetting extends Model {}
