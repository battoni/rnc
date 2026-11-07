<?php

use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use Database\Seeders\ManagerSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * A project can deny a key to the seeded member without editing a synced file — the same seam as
 * `manager-exclusions.local.php`.
 *
 * Found in largatudoevai (2026-09, Leonardo's QA): its catalog groups live in
 * `permission-groups.local.php`, and `ManagerSeeder` handed every key of them to the member, so an
 * ordinary member could create and delete catalog rows. The only fix available was an edit to the
 * synced seeder. These tests point the helper at a fixture so rnc's own (empty) file is never
 * written.
 */

beforeEach(function () {
    $this->originalUserExclusionsPath = PermissionGroupsHelper::$userExclusionsPath;
});

afterEach(function () {
    PermissionGroupsHelper::$userExclusionsPath = $this->originalUserExclusionsPath;
});

function userExclusionsFixture(array $keys): string
{
    $path = tempnam(sys_get_temp_dir(), 'user-exclusions');

    file_put_contents($path, '<?php return '.var_export($keys, true).';');

    return $path;
}

function userExclusionsRoleKeys(string $slug): array
{
    $role = Role::where('slug', $slug)->firstOrFail();

    return RolePermission::where('role_id', $role->id)->where('value', true)->pluck('permission_key')->all();
}

describe('the local user exclusions', function () {
    it('takes a listed key away from the member and from nobody else', function () {
        PermissionGroupsHelper::$userExclusionsPath = userExclusionsFixture(['ideas.create']);

        $this->seed([RoleSeeder::class, SuperAdminSeeder::class, ManagerSeeder::class]);

        expect(userExclusionsRoleKeys('user'))->not->toContain('ideas.create')
            ->and(userExclusionsRoleKeys('user'))->toContain('ideas.read')
            ->and(userExclusionsRoleKeys('manager'))->toContain('ideas.create');
    });

    it('adds to the core exclusions instead of replacing them', function () {
        PermissionGroupsHelper::$userExclusionsPath = userExclusionsFixture(['ideas.create']);

        $this->seed([RoleSeeder::class, SuperAdminSeeder::class, ManagerSeeder::class]);

        expect(userExclusionsRoleKeys('user'))->not->toContain('permissions.update')
            ->and(userExclusionsRoleKeys('user'))->not->toContain('users.read')
            ->and(userExclusionsRoleKeys('user'))->not->toContain('design.read');
    });

    it('can only take keys away: an unknown key grants nothing', function () {
        PermissionGroupsHelper::$userExclusionsPath = userExclusionsFixture(['billing.refund']);

        $this->seed([RoleSeeder::class, SuperAdminSeeder::class, ManagerSeeder::class]);

        expect(userExclusionsRoleKeys('user'))->not->toContain('billing.refund')
            ->and(userExclusionsRoleKeys('user'))->toContain('ideas.create');
    });

    it('reads nothing when a project has no local file yet', function () {
        PermissionGroupsHelper::$userExclusionsPath = sys_get_temp_dir().'/does-not-exist-user.local.php';

        expect(PermissionGroupsHelper::getLocalUserExclusions())->toBe([]);
    });
});
