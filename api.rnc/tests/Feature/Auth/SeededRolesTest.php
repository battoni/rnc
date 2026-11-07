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
 * F6a gives Reinaldo `fiscal.import` (the NF-e upload, D14); F7 gives the team — and so Reinaldo —
 * `fiscal.read` (NF-e readiness and supplier credit, D14).
 * rnc-3 F1 declares Phase 3's seven keys: the operator's only, until each phase grants its own.
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

    it('give the team the pipeline and the Reforma assistant', function () {
        expect(seededRoleKeys('equipe'))->toBe([
            'agenda.read',
            'assistant.ask',
            'fiscal.read',
            'home.read',
            'leads.read',
            'leads.update',
            'notifications.read',
        ]);
    });

    it('give Reinaldo the team keys plus the network, the numbers, the repricing, the channel, the knowledge base, the contract alert and the briefing', function () {
        expect(seededRoleKeys('ceo'))->toBe([
            'agenda.read',
            'assistant.ask',
            'assistant.manage',
            'briefing.read',
            'channel.read',
            'clients.import',
            'contracts.manage',
            'fiscal.import',
            'fiscal.read',
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

    it("keep Phase 3's keys with the operator until their own phase grants them (rnc-3 R28)", function () {
        $phase3 = ['brands.manage', 'channel.read', 'channel.manage', 'assistant.ask', 'assistant.manage', 'contracts.manage', 'briefing.read'];
        $granted = ['ceo' => ['assistant.ask', 'assistant.manage', 'briefing.read', 'channel.read', 'contracts.manage'], 'equipe' => ['assistant.ask'], 'parceiro' => []];

        expect(seededRoleKeys('operador'))->toContain(...$phase3);

        foreach ($granted as $slug => $keys) {
            expect(array_values(array_intersect(seededRoleKeys($slug), $phase3)))->toBe($keys);
        }
    });

    it('let the team ask the assistant and keep retiring notes and the gaps with Reinaldo and the operator (rnc-3 F4, AM4)', function () {
        expect(seededRoleKeys('equipe'))->toContain('assistant.ask')->not->toContain('assistant.manage')
            ->and(seededRoleKeys('ceo'))->toContain('assistant.ask', 'assistant.manage');
    });

    it('keep the channel\'s writes and its price with the operator: Reinaldo reads it (rnc-3 F3a, R9)', function () {
        expect(seededRoleKeys('ceo'))->toContain('channel.read')->not->toContain('channel.manage')
            ->and(seededRoleKeys('equipe'))->not->toContain('channel.read');
    });

    it('give Reinaldo the contract alert and keep it from the team (rnc-3 F6, R18)', function () {
        expect(seededRoleKeys('ceo'))->toContain('contracts.manage')
            ->and(seededRoleKeys('equipe'))->not->toContain('contracts.manage');
    });

    it('give Reinaldo the briefing and keep it from the team and the partner (rnc-3 F8, R23)', function () {
        expect(seededRoleKeys('ceo'))->toContain('briefing.read')
            ->and(seededRoleKeys('equipe'))->not->toContain('briefing.read')
            ->and(seededRoleKeys('parceiro'))->not->toContain('briefing.read');
    });

    it('keep the staff directory and the permission matrix with the operator', function () {
        foreach (['ceo', 'equipe', 'parceiro'] as $slug) {
            expect(seededRoleKeys($slug))->not->toContain('users.read')
                ->and(seededRoleKeys($slug))->not->toContain('permissions.update');
        }
    });
});
