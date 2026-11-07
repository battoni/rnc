<?php

namespace App\Modules\Partner\Resources;

use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Partner\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A partner's portal login (rnc-2 R2), built from the Partner with its `account` loaded. Closed by
 * default: the password appears only through `withPassword()`, in the one response that issued it
 * (arcus "A resource is closed by default").
 *
 * @mixin Partner
 */
class PartnerAccessResource extends JsonResource
{
    private ?string $password = null;

    public function withPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $account = $this->account;

        return [
            'partner_id' => $this->id,
            'user_id' => $account?->id,
            'username' => $account?->username,
            'email' => $account?->email,
            'active' => $account?->status === UserStatus::ACTIVE,
            'granted_at' => $this->access_granted_at?->toIso8601String(),
            'revoked_at' => $this->access_revoked_at?->toIso8601String(),
            ...($this->password !== null ? ['password' => $this->password] : []),
        ];
    }
}
