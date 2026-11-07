<?php

use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use Database\Seeders\FirmSeeder;
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
 *
 * The table is the plan review's AM8 (rnc-1, 07/11/2026): the team works the pipeline, Reinaldo
 * also runs the network, the numbers and the repricing, and the operator holds everything.
 * rnc-2 F1 adds `parceiro` (R1): the portal key and nothing else, and Reinaldo's `ledger.read`;
 * F6a gives Reinaldo `fiscal.import` (the NF-e upload, D14).
 */

beforeEach(fn () => $this->seed([FirmSeeder::class, RoleSeeder::class, SuperAdminSeeder::class]));

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

describe('the seeded roles', function () {
    it('are exactly the four of the product', function () {
        expect(Role::pluck('slug')->sort()->values()->all())->toBe(['ceo', 'equipe', 'operador', 'parceiro']);
    });

    it('give a partner only the portal (rnc-2 R1)', function () {
        expect(seededRoleKeys('parceiro'))->toBe(['portal.access']);
    });

    it('give the team only the pipeline', function () {
        expect(seededRoleKeys('equipe'))->toBe([
            'agenda.read',
            'home.read',
            'leads.read',
            'leads.update',
            'notifications.read',
        ]);
    });

    it('give Reinaldo the team keys plus the network, the numbers and the repricing', function () {
        expect(seededRoleKeys('ceo'))->toBe([
            'agenda.read',
            'clients.import',
            'fiscal.import',
            'home.read',
            'leads.read',
            'leads.update',
            'ledger.read',
            'metrics.read',
            'notifications.read',
            'partners.manage',
            'referrals.read',
            'repricing.manage',
        ]);
    });

    it('give the operator every key there is', function () {
        $expected = collect(PermissionGroupsHelper::getPermissionGroupsStructure())
            ->flatMap(fn (array $group) => array_column($group['permissions'], 'key'))
            ->sort()
            ->values()
            ->all();

        expect(seededRoleKeys('operador'))->toBe($expected);
    });

    it('keep the staff directory and the permission matrix with the operator', function () {
        foreach (['ceo', 'equipe', 'parceiro'] as $slug) {
            expect(seededRoleKeys($slug))->not->toContain('users.read')
                ->and(seededRoleKeys($slug))->not->toContain('permissions.update');
        }
    });
});
