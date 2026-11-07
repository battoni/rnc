<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['role_id', 'permission_key', 'value'])]
class RolePermission extends Model
{
    /*
     * `role_permissions` has no timestamp columns — the sibling `user_permissions` model already
     * says this and the tables are shaped the same way. Without it any `RolePermission::create()`
     * throws, which stayed hidden only because the seeder writes through `insert()` and so
     * bypasses the model entirely.
     */
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'value' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
