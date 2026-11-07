<?php

namespace App\Modules\Idea\Services;

use App\Models\User;
use App\Modules\Auth\Services\PermissionService;

class IdeaAccessService
{
    private const MODERATE_PERMISSION = 'ideas.manage';

    public function __construct(
        private PermissionService $permissionService
    ) {}

    /**
     * Whether this account is the maintainer for the board — the one who sets a status, writes
     * the public note, hides a row and merges duplicates.
     *
     * Asked in the service rather than only at the route, because the same permission changes
     * what a READ returns: a moderator sees hidden rows, everyone else must not learn they
     * exist.
     */
    public function canModerate(User $user): bool
    {
        return $this->can($user, self::MODERATE_PERMISSION);
    }

    /**
     * Any key, memoised the same way. Novidades asks for `news.manage` through here rather than
     * reaching for PermissionService itself, so every permission read in this module goes through
     * one place and gets the same per-request memoisation.
     */
    public function can(User $user, string $permission): bool
    {
        return in_array($permission, $this->permissionService->forUser($user), true);
    }
}
