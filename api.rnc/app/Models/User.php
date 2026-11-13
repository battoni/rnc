<?php

namespace App\Models;

use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\UserPermission;
use App\Modules\Firm\Models\Firm;
use App\Modules\Partner\Models\Partner;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property UserStatus|null $status Cast in `casts()`. Stated here because static analysis reads the
 *                                   column as a string and would call every `=== UserStatus::…`
 *                                   comparison dead code. Null only on a model not yet saved.
 * @property int|null $partner_id The partner whose portal login this is (rnc-2 R1); null for staff.
 */
#[Fillable(['name', 'last_name', 'username', 'role_id', 'firm_id', 'partner_id', 'is_specialist', 'status', 'email', 'password',
    // These arrive with a registration. Outside this list `create()` discards them in
    // SILENCE — the pending row held the document and the date, and the account was born
    // without either.
    'phone', 'profile_type', 'cpf', 'birth_date'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'email_verified_at' => 'datetime',
            'is_specialist' => 'boolean',
            'partner_id' => 'integer',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Users are not firm-scoped themselves: sign-in looks a username up before any firm is known.
     * The firm they belong to is what scopes everything they read (`ResolveCurrentFirm`).
     *
     * @return BelongsTo<Firm, $this>
     */
    public function firm(): BelongsTo
    {
        return $this->belongsTo(Firm::class);
    }

    /**
     * The partner a portal login belongs to (rnc-2 R1). `Partner` is firm-scoped, so this resolves
     * only inside the current firm — a login pointing at another firm's partner finds none
     * (`ResolveCurrentPartner`, plan AM1).
     *
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function userPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }
}
