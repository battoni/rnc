<?php

/*
 * Project-owned permission groups — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * The Reforma hub's keys (rnc-1, plan AM8). They are declared together in F1 so the role seeder can
 * hand them out once; each endpoint that needs one arrives in its own phase — pipeline and agenda
 * in F5, the bell in F4, partners and the trail in F6, metrics in F7, repricing and the client
 * import in F8. Who holds which is `RoleSeeder`'s table.
 */

return [
    [
        'id' => 'leads',
        'nameKey' => 'rolesAndPermissions.groups.leads',
        'icon' => 'pi pi-filter',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'leads.read', 'value' => false],
            ['labelKey' => 'rolesAndPermissions.permissions.update', 'key' => 'leads.update', 'value' => false],
        ],
    ],
    [
        'id' => 'agenda',
        'nameKey' => 'rolesAndPermissions.groups.agenda',
        'icon' => 'pi pi-calendar',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'agenda.read', 'value' => false],
        ],
    ],
    [
        'id' => 'notifications',
        'nameKey' => 'rolesAndPermissions.groups.notifications',
        'icon' => 'pi pi-bell',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'notifications.read', 'value' => false],
        ],
    ],
    [
        'id' => 'partners',
        'nameKey' => 'rolesAndPermissions.groups.partners',
        'icon' => 'pi pi-sitemap',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'partners.manage', 'value' => false],
        ],
    ],
    [
        'id' => 'referrals',
        'nameKey' => 'rolesAndPermissions.groups.referrals',
        'icon' => 'pi pi-directions',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'referrals.read', 'value' => false],
        ],
    ],
    [
        'id' => 'metrics',
        'nameKey' => 'rolesAndPermissions.groups.metrics',
        'icon' => 'pi pi-chart-bar',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'metrics.read', 'value' => false],
        ],
    ],
    [
        'id' => 'repricing',
        'nameKey' => 'rolesAndPermissions.groups.repricing',
        'icon' => 'pi pi-percentage',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'repricing.manage', 'value' => false],
            ['labelKey' => 'rolesAndPermissions.permissions.import', 'key' => 'clients.import', 'value' => false],
        ],
    ],
];
