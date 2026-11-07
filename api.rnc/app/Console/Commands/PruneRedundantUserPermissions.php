<?php

namespace App\Console\Commands;

use App\Modules\Auth\Models\UserPermission;
use App\Modules\Auth\Repositories\RoleRepository;
use App\Modules\Auth\Repositories\UserPermissionRepository;
use App\Modules\Auth\Repositories\UserRepository;
use Illuminate\Console\Command;

/**
 * Delete the per-user permission rows that merely repeat what the profile already says.
 *
 * Accounts made before UserService learned to store only divergences carry a full copy of the
 * matrix, frozen on the day they were created — which made the profile decoration: editing one
 * reached nobody. This removes the copies and keeps the disagreements.
 *
 * Safe to run twice, and safe to run at all: a row goes only when the profile already gives that
 * exact answer, so nobody's effective permissions change. Run it with --dry-run first.
 */
class PruneRedundantUserPermissions extends Command
{
    protected $signature = 'permissions:prune-redundant {--dry-run : Report what would go, and change nothing}';

    protected $description = "Remove user permission rows that agree with the user's profile";

    public function handle(
        RoleRepository $roleRepository,
        UserPermissionRepository $userPermissionRepository,
        UserRepository $userRepository,
    ): int {
        $isDryRun = (bool) $this->option('dry-run');
        $rolePermissions = [];
        $removed = 0;

        foreach ($userRepository->cursorAllUsers() as $user) {
            $rows = $userPermissionRepository->findUserPermissionRowsByUserId($user->id);

            if ($rows->isEmpty()) {
                continue;
            }

            if ($user->role_id && ! isset($rolePermissions[$user->role_id])) {
                $rolePermissions[$user->role_id] = $roleRepository->getRolePermissions($user->role_id);
            }

            $fromRole = $user->role_id ? $rolePermissions[$user->role_id] : [];

            $redundant = $rows->filter(
                fn (UserPermission $row) => (bool) $row->value === (bool) ($fromRole[$row->permission_key] ?? false)
            );

            $this->line(sprintf(
                '%-24s %3d redundant, %d divergent kept',
                $user->username,
                $redundant->count(),
                $rows->count() - $redundant->count()
            ));

            if (! $isDryRun && $redundant->isNotEmpty()) {
                $userPermissionRepository->deleteUserPermissionsByIds($redundant->pluck('id')->all());
            }

            $removed += $redundant->count();
        }

        $this->newLine();
        $this->info($isDryRun
            ? "{$removed} row(s) would be removed. Run without --dry-run to apply."
            : "{$removed} row(s) removed.");

        return self::SUCCESS;
    }
}
