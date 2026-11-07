<?php

namespace App\Modules\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'email',
    'phone',
    'first_name',
    'last_name',
    'password',
    'profile_type',
    'cpf',
    'birth_date',
    'code_hash',
    'expires_at',
    'attempts',
])]
#[Hidden(['password', 'code_hash'])]
class PendingRegistration extends Model
{
    /** Mirrors the column default, so a row just created reads 0 rather than null. */
    protected $attributes = [
        'attempts' => 0,
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'birth_date' => 'date',
            'expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
