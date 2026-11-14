<?php

namespace App\Modules\Auth\Resources;

use App\Modules\Auth\Enums\UserStatus;
use App\Modules\Auth\Services\RoleService;
use App\Modules\Firm\Models\Firm;
use App\Modules\Firm\Resources\FirmSummaryResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    private bool $withPlatform = false;

    private ?Firm $viewAs = null;

    /**
     * Adds what the signed-in shell boots from (rnc-4 R9, R20) — for `GET auth/me` only; a user in a
     * list does not carry it: `firm` (`FirmSummaryResource`, null for a firm-less account) and
     * `platform` — the product's name, whether this is the platform operator, and the firm being
     * viewed as (`ViewAsFirm`, null until F4c).
     */
    public function withPlatform(?Firm $viewAs = null): static
    {
        $this->withPlatform = true;
        $this->viewAs = $viewAs;

        return $this;
    }

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
            ...($this->withPlatform ? [
                'firm' => $user->firm ? (new FirmSummaryResource($user->firm))->resolve($request) : null,
                'platform' => [
                    'name' => (string) config('platform.name'),
                    'is_operator' => $user->role?->slug === RoleService::ADMINISTRATOR_SLUG,
                    'view_as' => $this->viewAs ? ['slug' => $this->viewAs->slug, 'name' => $this->viewAs->name] : null,
                ],
            ] : []),
        ];
    }
}
