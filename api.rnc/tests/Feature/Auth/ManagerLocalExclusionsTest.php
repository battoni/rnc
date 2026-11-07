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
 * A project can deny a key to the Manager without editing a synced file.
 *
 * Found in largatudoevai (2026-09): the only way to keep a feature away from the seeded Manager was
 * to add its key to `$excludedManagerPermissionKeys` inside `ManagerSeeder` — a synced file, so the
 * next rnc update put the key back and a re-seed handed the feature over in silence.
 * `manager-exclusions.local.php` is the LOCAL half; these tests point the helper at a fixture so
 * rnc's own (empty) file is never written.
 */

beforeEach(function () {
    $this->originalManagerExclusionsPath = PermissionGroupsHelper::$managerExclusionsPath;
});

afterEach(function () {
    PermissionGroupsHelper::$managerExclusionsPath = $this->originalManagerExclusionsPath;
});

function managerExclusionsFixture(array $keys): string
{
    $path = tempnam(sys_get_temp_dir(), 'manager-exclusions');

    file_put_contents($path, '<?php return '.var_export($keys, true).';');

    return $path;
}

function managerExclusionsRoleKeys(string $slug): array
{
    $role = Role::where('slug', $slug)->firstOrFail();

    return RolePermission::where('role_id', $role->id)->where('value', true)->pluck('permission_key')->all();
}

describe('the local manager exclusions', function () {
    it('ships empty in rnc, so the seeded lists stay the core ones', function () {
        expect(PermissionGroupsHelper::getLocalManagerExclusions())->toBe([]);
    });

    it('takes a listed key away from the manager and from nobody else', function () {
        PermissionGroupsHelper::$managerExclusionsPath = managerExclusionsFixture(['tips.manage']);

        $this->seed([RoleSeeder::class, SuperAdminSeeder::class, ManagerSeeder::class]);

        expect(managerExclusionsRoleKeys('manager'))->not->toContain('tips.manage')
            ->and(managerExclusionsRoleKeys('manager'))->toContain('tips.read')
            ->and(managerExclusionsRoleKeys('administrator'))->toContain('tips.manage');
    });

    it('adds to the core exclusions instead of replacing them', function () {
        PermissionGroupsHelper::$managerExclusionsPath = managerExclusionsFixture(['tips.manage']);

        $this->seed([RoleSeeder::class, SuperAdminSeeder::class, ManagerSeeder::class]);

        expect(managerExclusionsRoleKeys('manager'))->not->toContain('design.read');
    });

    it('can only take keys away: an unknown key grants nothing', function () {
        PermissionGroupsHelper::$managerExclusionsPath = managerExclusionsFixture(['billing.refund']);

        $this->seed([RoleSeeder::class, SuperAdminSeeder::class, ManagerSeeder::class]);

        expect(managerExclusionsRoleKeys('manager'))->not->toContain('billing.refund')
            ->and(managerExclusionsRoleKeys('manager'))->toContain('tips.manage');
    });

    it('reads nothing when a project has no local file yet', function () {
        PermissionGroupsHelper::$managerExclusionsPath = sys_get_temp_dir().'/does-not-exist.local.php';

        expect(PermissionGroupsHelper::getLocalManagerExclusions())->toBe([]);
    });
});
