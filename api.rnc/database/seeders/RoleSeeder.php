<?php

namespace Database\Seeders;

use App\Modules\Auth\Helpers\PermissionGroupsHelper;
use App\Modules\Auth\Models\Role;
use App\Modules\Auth\Models\RolePermission;
use App\Modules\Auth\Services\RoleService;
use App\Modules\Partner\Services\PartnerAccessService;
use Illuminate\Database\Seeder;

/**
 * The hub's four roles (R2, plan AM8; `parceiro` from rnc-2 F1), with their keys written out.
 *
 * - `operador` — Battoni Dev, who runs the product. Every key there is, always: it is the role
 *   `RoleService::ADMINISTRATOR_SLUG` protects from losing a key or being deleted.
 * - `ceo` — Reinaldo. The team's keys plus the network, the numbers and the repricing.
 * - `equipe` — Franco's team: the pipeline, the agenda and the bell. A member with
 *   `users.is_specialist` is also bookable by a visitor.
 * - `parceiro` — a partner's portal login (rnc-2 R1): the portal key and nothing else, so every
 *   back-office route refuses it. The login also needs `users.partner_id`; the operator holds
 *   `portal.access` too and is refused by the portal for lacking one.
 *
 * Phase 2's other keys are declared from rnc-2 F1 on (the operator holds them at once) and granted
 * to Reinaldo in their own phase: `ledger.read` with F4, `fiscal.import` with F6a (the NF-e upload),
 * `fiscal.read` with F7 — to the team too, so Reinaldo inherits it (D14: NF-e readiness and supplier
 * credit) — and `radar.manage` with F9.
 *
 * Re-running the seeder rewrites the four matrices to exactly this table. A key added to a role
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
        'fiscal.read',
    ];

    public const CEO_EXTRA_KEYS = [
        'partners.manage',
        'referrals.read',
        'metrics.read',
        'repricing.manage',
        'clients.import',
        'ledger.read',
        'fiscal.import',
        'radar.manage',
    ];

    public const PARCEIRO_KEYS = [
        'portal.access',
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
        $this->seedRole(PartnerAccessService::PARTNER_ROLE_SLUG, 'Parceiro', 'Parceiro da rede: só o portal do parceiro', self::PARCEIRO_KEYS);
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
