<?php

namespace App\Modules\Auth\Models;

use Carbon\CarbonInterface;
use Database\Factories\LoginCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-time code for signing in by phone. Only its hash is stored.
 *
 * @property int $id
 * @property string $phone
 * @property string $code_hash
 * @property CarbonInterface $expires_at
 * @property int $attempts
 * @property CarbonInterface|null $consumed_at
 */
#[Fillable(['phone', 'code_hash', 'expires_at', 'attempts', 'consumed_at'])]
#[Hidden(['code_hash'])]
#[UseFactory(LoginCodeFactory::class)]
class LoginCode extends Model
{
    /** @use HasFactory<LoginCodeFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'attempts' => 'integer',
            'consumed_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
