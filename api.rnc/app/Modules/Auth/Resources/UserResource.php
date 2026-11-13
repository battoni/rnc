<?php

namespace App\Modules\Auth\Resources;

use App\Modules\Auth\Enums\UserStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'email' => $user->email,
            // A boolean, not the timestamp: the screen asks "is this address proven?", and the
            // moment it was proven is not the client's business.
            'email_verified' => $user->email_verified_at !== null,
            'phone' => $user->phone,
            'profile_type' => $user->profile_type,
            'role' => $user->role?->name,
            'role_slug' => $user->role?->slug,
            // Which firm's hub this person works in, and whether a visitor can book them (rnc-1 R2).
            'firm_slug' => $user->firm?->slug,
            'is_specialist' => (bool) $user->is_specialist,
            // The partner a portal login belongs to (rnc-2 R1); null for every staff account.
            'partner_id' => $user->partner_id === null ? null : (int) $user->partner_id,
            'status' => $user->status->value ?? UserStatus::ACTIVE->value,
            'permissions' => $user->getAttribute('permissions') ?? [],
        ];
    }
}
