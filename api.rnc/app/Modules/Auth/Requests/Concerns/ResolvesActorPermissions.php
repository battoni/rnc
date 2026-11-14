<?php

namespace App\Modules\Auth\Requests\Concerns;

use App\Http\Middleware\EnsurePlatformOperator;
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

    /** The firm-less `operador` (rnc-4 F4a): the one actor who may give a role outside a firm's team (`TeamRole`). */
    protected function actorIsPlatformOperator(): bool
    {
        $actor = $this->user();

        return $actor instanceof User && EnsurePlatformOperator::isPlatformOperator($actor);
    }
}
