<?php

use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use Database\Seeders\ManagerSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * What a re-run of the seeders hands out, key by key.
 *
 * A seeder is also a recovery command: it gets run to restore somebody's access, and it rewrites
 * every role on the way past. In lifeshifter that handed four unreleased screens to an ordinary
 * member, and nothing failed or warned. So the lists are written out here. Adding a key is allowed —
 * it just has to be a decision someone made in this file, in daylight, rather than a side effect.
 */

beforeEach(fn () => $this->seed([RoleSeeder::class, SuperAdminSeeder::class, ManagerSeeder::class]));

function seededRoleKeys(string $slug): array
{
    $role = Role::where('slug', $slug)->firstOrFail();

    return RolePermission::where('role_id', $role->id)
        ->where('value', true)
        ->pluck('permission_key')
        ->sort()
        ->values()
        ->all();
}

/*
 * Written from the seeders as they stand. The question this list used to carry — every member
 * receiving `users.read` (the whole account directory) and `users.reset_password` — was answered on
 * 2026-09-20: both are withheld (ARC-15). The directory was readable by anybody who signed
 * themselves up, and `users.read` was also the documentation portal's "this person is staff"
 * signal, so a member read the internal docs (LP-DOCS-01). `tips.read` followed on 2026-09-22: it
 * is the tips' back office (history, editors' names), and the tips themselves need no key.
 */
describe('the seeded member role', function () {
    it('holds exactly these keys', function () {
        expect(seededRoleKeys('user'))->toBe([
            'home.read',
            'ideas.create',
            'ideas.read',
        ]);
    });
});

describe('the seeded manager role', function () {
    it('holds exactly these keys', function () {
        expect(seededRoleKeys('manager'))->toBe([
            'home.read',
            'ideas.create',
            'ideas.manage',
            'ideas.read',
            'news.manage',
            'permissions.read',
            'permissions.update',
            'tips.manage',
            'tips.read',
            'users.archive',
            'users.create',
            'users.delete',
            'users.read',
            'users.reset_password',
            'users.search',
            'users.update',
        ]);
    });
});
