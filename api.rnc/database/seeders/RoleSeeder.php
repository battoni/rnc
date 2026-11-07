<?php

namespace Database\Seeders;

use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use App\Modules\Auth\Services\RoleService;
use Illuminate\Database\Seeder;

/**
 * The hub's three roles (R2, plan AM8), with their keys written out.
 *
 * - `operador` — Battoni Dev, who runs the product. Every key there is, always: it is the role
 *   `RoleService::ADMINISTRATOR_SLUG` protects from losing a key or being deleted.
 * - `ceo` — Reinaldo. The team's keys plus the network, the numbers and the repricing.
 * - `equipe` — Franco's team: the pipeline, the agenda and the bell. A member with
 *   `users.is_specialist` is also bookable by a visitor.
 *
 * Re-running the seeder rewrites the three matrices to exactly this table. A key added to a role
 * is a line here, and `SeededRolesTest` states the result.
 */
class RoleSeeder extends Seeder
{
    public const EQUIPE_KEYS = [
        'home.read',
        'leads.read',
        'leads.update',
        'agenda.read',
        'notifications.read',
    ];

    public const CEO_EXTRA_KEYS = [
        'partners.manage',
        'referrals.read',
        'metrics.read',
        'repricing.manage',
        'clients.import',
    ];

    public function run(): void
    {
        $everyKey = collect(PermissionGroupsHelper::getPermissionGroupsStructure())
            ->flatMap(fn (array $group) => array_column($group['permissions'], 'key'))
            ->values()
            ->all();

        $this->seedRole(RoleService::ADMINISTRATOR_SLUG, 'Operador', 'Battoni Dev: opera o produto, todas as permissões', $everyKey);
        $this->seedRole('ceo', 'CEO', 'Reinaldo: equipe, rede de parceiros, métricas e reajuste', [...self::EQUIPE_KEYS, ...self::CEO_EXTRA_KEYS]);
        $this->seedRole('equipe', 'Equipe', 'Equipe da Franco: pipeline, agenda e notificações', self::EQUIPE_KEYS);
    }

    /**
     * @param  list<string>  $keys
     */
    private function seedRole(string $slug, string $name, string $description, array $keys): void
    {
        $role = Role::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => $description]);

        RolePermission::where('role_id', $role->id)->delete();
        RolePermission::insert(array_map(
            fn (string $key) => ['role_id' => $role->id, 'permission_key' => $key, 'value' => true],
            $keys,
        ));
    }
}
