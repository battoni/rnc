<?php

namespace App\Modules\Auth\Helpers;

class PermissionGroupsHelper
{
    /**
     * Where a project lists the keys its Manager is denied (LOCAL, never synced). A property rather
     * than a literal so a test can point it at a fixture without writing the project's own file.
     */
    public static string $managerExclusionsPath = __DIR__.'/manager-exclusions.local.php';

    /**
     * Where a project lists the keys its seeded member (`user`) is denied (LOCAL, never synced).
     * Same testability as `$managerExclusionsPath`.
     */
    public static string $userExclusionsPath = __DIR__.'/user-exclusions.local.php';

    /**
     * Permission keys the project denies the seeded Manager, on top of the core exclusions that
     * `ManagerSeeder` keeps itself.
     *
     * @return list<string>
     */
    public static function getLocalManagerExclusions(): array
    {
        if (! file_exists(self::$managerExclusionsPath)) {
            return [];
        }

        return array_values(require self::$managerExclusionsPath);
    }

    /**
     * Permission keys the project denies the seeded member, on top of the core exclusions that
     * `ManagerSeeder` keeps itself.
     *
     * @return list<string>
     */
    public static function getLocalUserExclusions(): array
    {
        if (! file_exists(self::$userExclusionsPath)) {
            return [];
        }

        return array_values(require self::$userExclusionsPath);
    }

    public static function getPermissionGroupsStructure(): array
    {
        $groups = [
            [
                'id' => 'users',
                'nameKey' => 'rolesAndPermissions.groups.users',
                'icon' => 'pi pi-users',
                'permissions' => [
                    ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'users.read', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.search', 'key' => 'users.search', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.create', 'key' => 'users.create', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.update', 'key' => 'users.update', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.resetPassword', 'key' => 'users.reset_password', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.archive', 'key' => 'users.archive', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.delete', 'key' => 'users.delete', 'value' => false],
                ],
            ],
            [
                'id' => 'home',
                'nameKey' => 'rolesAndPermissions.groups.home',
                'icon' => 'pi pi-home',
                'permissions' => [
                    ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'home.read', 'value' => false],
                ],
            ],
            [
                'id' => 'permissions',
                'nameKey' => 'rolesAndPermissions.groups.permissions',
                'icon' => 'pi pi-key',
                'permissions' => [
                    ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'permissions.read', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.update', 'key' => 'permissions.update', 'value' => false],
                ],
            ],
            /*
             * IDEAS — the feedback board. Three keys, not the usual read/manage pair, because the
             * board has three genuinely different acts.
             *
             * `create` is split from `read` so a noisy account can be muted without being blinded:
             * taking away someone's ability to write should not take away their ability to see
             * what the product is doing. `manage` is the maintainer's — status, the public note,
             * hiding and merging.
             */
            [
                'id' => 'ideas',
                'nameKey' => 'rolesAndPermissions.groups.ideas',
                'icon' => 'pi pi-lightbulb',
                'permissions' => [
                    ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'ideas.read', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.create', 'key' => 'ideas.create', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'ideas.manage', 'value' => false],
                ],
            ],
            /*
             * NEWS — its own key, split from `ideas.manage` when the changelog stopped being a
             * line of text and became something written to be read: a cover, a standfirst, an
             * article. Writing the product's announcements and moderating other people's ideas
             * are different jobs, and one can be granted without the other.
             */
            [
                'id' => 'news',
                'nameKey' => 'rolesAndPermissions.groups.news',
                'icon' => 'pi pi-megaphone',
                'permissions' => [
                    ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'news.manage', 'value' => false],
                ],
            ],
            /*
             * TIPS — the contextual help. `read` is the management view and the audit trail;
             * `manage` writes. Reading a tip where it renders needs neither: it is ambient help,
             * and gating it would blank the icon for exactly the people it exists for.
             */
            [
                'id' => 'tips',
                'nameKey' => 'rolesAndPermissions.groups.tips',
                'icon' => 'pi pi-info-circle',
                'permissions' => [
                    ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'tips.read', 'value' => false],
                    ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'tips.manage', 'value' => false],
                ],
            ],
            /*
             * DESIGN — the living references: Brand, Components, Features and Showcase. A
             * permission, not a role check, because a role check cannot be granted from the
             * permissions tab: gated on the administrator's slug, this was the one surface the
             * owner could not hand to anybody else. Read-only — the pages document the product and
             * change nothing.
             */
            [
                'id' => 'design',
                'nameKey' => 'rolesAndPermissions.groups.design',
                'icon' => 'pi pi-th-large',
                'permissions' => [
                    ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'design.read', 'value' => false],
                ],
            ],
        ];

        // Project-owned permission groups (LOCAL) — appended; the cortex sync never touches
        // permission-groups.local.php, so feature permissions survive every rnc update.
        $localGroups = __DIR__.'/permission-groups.local.php';

        if (file_exists($localGroups)) {
            $groups = array_merge($groups, require $localGroups);
        }

        return $groups;
    }
}
