<?php

use App\Models\User;
use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * The seeded install, stated exactly. It is small enough to state, and stating it is the point: an
 * install that quietly grows demo accounts is an install with extra passwords, and `migrate:fresh
 * --seed` runs on every deploy of this bootstrap.
 */
describe('database seed', function () {
    beforeEach(fn () => $this->seed());

    it('creates exactly the three bootstrap profiles', function () {
        expect(Role::pluck('slug')->sort()->values()->all())->toBe(['administrator', 'manager', 'user']);
    });

    it('gives the administrator every permission there is', function () {
        $expected = collect(PermissionGroupsHelper::getPermissionGroupsStructure())
            ->flatMap(fn (array $group) => array_column($group['permissions'], 'key'))
            ->sort()
            ->values()
            ->all();

        $granted = RolePermission::where('role_id', Role::where('slug', 'administrator')->value('id'))
            ->where('value', true)
            ->pluck('permission_key')
            ->sort()
            ->values()
            ->all();

        expect($granted)->toBe($expected);
    });

    it('creates exactly the bootstrap accounts', function () {
        expect(User::pluck('username')->sort()->values()->all())->toBe(['battoni', 'manager', 'user_without_permissions']);
    });

    it('leaves no throwaway test account behind', function () {
        expect(User::where('username', 'test-user')->orWhere('email', 'test@example.com')->count())->toBe(0);
    });

    it('does not let the member read the account directory', function () {
        /*
         * `users.read` returned every account's name, e-mail and phone to anybody who signed
         * themselves up, and the documentation portal read that same key as "this person is
         * staff" (ARC-15, LP-DOCS-01). `users.reset_password` went with it: a member has no
         * account but their own to recover.
         */
        $member = Role::where('slug', 'user')->firstOrFail();
        $keys = RolePermission::where('role_id', $member->id)->where('value', true)->pluck('permission_key')->all();

        expect($keys)->not->toContain('users.read')
            ->and($keys)->not->toContain('users.reset_password')
            // Nor the tips' back office: the history names the staff who edited each tip.
            ->and($keys)->not->toContain('tips.read');
    });
});
