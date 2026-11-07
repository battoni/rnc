<?php

namespace App\Modules\Auth\Requests\Concerns;

use App\Models\User;
use App\Modules\Auth\Services\PermissionService;

/**
 * The keys the person making the request holds, for the rules that refuse to hand out more than
 * that (`GrantableByActor`, `AssignableRole`).
 *
 * Resolved here rather than in each rule so the three requests that grant something ask the same
 * question, and so the answer comes from `PermissionService` — the one place role keys and personal
 * overrides are merged, and memoized per request.
 */
trait ResolvesActorPermissions
{
    /**
     * @return array<int, string>
     */
    protected function actorPermissionKeys(): array
    {
        $actor = $this->user();

        return $actor instanceof User ? app(PermissionService::class)->forUser($actor) : [];
    }
}
