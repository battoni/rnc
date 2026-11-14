<?php

namespace App\Modules\Platform\Models;

use App\Models\User;
use App\Modules\Firm\Models\Firm;
use App\Modules\Platform\Enums\ViewAsEndReason;
use Carbon\CarbonInterface;
use Database\Factories\ViewAsSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One time the platform operator entered a firm through "Acessar como suporte" (rnc-4 R26, D20).
 * The platform's own audit, so NOT firm-scoped (no `BelongsToFirm`): `firm_id` is the firm viewed,
 * never the request's.
 *
 * @property int $id
 * @property int $user_id
 * @property int $firm_id
 * @property string|null $ip_address
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $ended_at
 * @property ViewAsEndReason|null $end_reason
 * @property-read User|null $operator
 * @property-read Firm|null $firm
 */
#[Fillable(['user_id', 'firm_id', 'ip_address', 'started_at', 'ended_at', 'end_reason'])]
#[UseFactory(ViewAsSessionFactory::class)]
class ViewAsSession extends Model
{
    /** @use HasFactory<ViewAsSessionFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $table = 'platform_view_as_sessions';

    /** @return BelongsTo<User, $this> */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Firm, $this> */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'end_reason' => ViewAsEndReason::class,
        ];
    }
}
