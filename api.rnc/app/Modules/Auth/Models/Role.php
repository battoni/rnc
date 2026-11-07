<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'description', 'slug'])]
class Role extends Model
{
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Role $role): void {
            if (empty($role->slug)) {
                $role->slug = Str::slug($role->name);
            }
        });
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function getPermissionsMap(): array
    {
        return $this->rolePermissions
            ->pluck('value', 'permission_key')
            ->toArray();
    }
}
