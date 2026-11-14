<?php

/*
 * Project-owned permission groups — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * The Reforma hub's keys (rnc-1, plan AM8). They are declared together in F1 so the role seeder can
 * hand them out once; each endpoint that needs one arrives in its own phase — pipeline and agenda
 * in F5, the bell in F4, partners and the trail in F6, metrics in F7, repricing and the client
 * import in F8. Who holds which is `RoleSeeder`'s table.
 *
 * Phase 2 (rnc-2 F1) declares its five keys at once, the same way: `portal.access` (the partner's
 * portal, F1), `ledger.read` (the network ledger, F4), `fiscal.read` / `fiscal.import` (NF-e
 * readiness and supplier credit, F6a–F8) and `radar.manage` (the Radar, F9). Later phases only grant.
 *
 * Phase 3 (rnc-3 F1, R28) declares its seven keys at once too: `brands.manage` (the sub-brands, F1),
 * `channel.read` / `channel.manage` (the RNC channel, F3a), `assistant.ask` / `assistant.manage` (the
 * Reforma assistant, F4), `contracts.manage` (the contract alert, F6) and `briefing.read` (Reinaldo's
 * briefing, F8). The operator holds them at once; each is granted to ceo or equipe in its own phase.
 *
 * rnc-4 F4a adds `platform.firms` / `platform.consent` (the operator's console, R24, R25). They are
 * the operator's alone and stay so: `RoleService::OPERATOR_ONLY_KEY_PREFIX` refuses them to any
 * other role, and the console's routes also ask for the firm-less operator itself.
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
    [
        'id' => 'portal',
        'nameKey' => 'rolesAndPermissions.groups.portal',
        'icon' => 'pi pi-briefcase',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.access', 'key' => 'portal.access', 'value' => false],
        ],
    ],
    [
        'id' => 'ledger',
        'nameKey' => 'rolesAndPermissions.groups.ledger',
        'icon' => 'pi pi-wallet',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'ledger.read', 'value' => false],
        ],
    ],
    [
        'id' => 'fiscal',
        'nameKey' => 'rolesAndPermissions.groups.fiscal',
        'icon' => 'pi pi-file',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'fiscal.read', 'value' => false],
            ['labelKey' => 'rolesAndPermissions.permissions.import', 'key' => 'fiscal.import', 'value' => false],
        ],
    ],
    [
        'id' => 'radar',
        'nameKey' => 'rolesAndPermissions.groups.radar',
        'icon' => 'pi pi-compass',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'radar.manage', 'value' => false],
        ],
    ],
    [
        'id' => 'brands',
        'nameKey' => 'rolesAndPermissions.groups.brands',
        'icon' => 'pi pi-palette',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'brands.manage', 'value' => false],
        ],
    ],
    [
        'id' => 'channel',
        'nameKey' => 'rolesAndPermissions.groups.channel',
        'icon' => 'pi pi-share-alt',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'channel.read', 'value' => false],
            ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'channel.manage', 'value' => false],
        ],
    ],
    [
        'id' => 'assistant',
        'nameKey' => 'rolesAndPermissions.groups.assistant',
        'icon' => 'pi pi-comments',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.ask', 'key' => 'assistant.ask', 'value' => false],
            ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'assistant.manage', 'value' => false],
        ],
    ],
    [
        'id' => 'contracts',
        'nameKey' => 'rolesAndPermissions.groups.contracts',
        'icon' => 'pi pi-file-edit',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'contracts.manage', 'value' => false],
        ],
    ],
    [
        'id' => 'briefing',
        'nameKey' => 'rolesAndPermissions.groups.briefing',
        'icon' => 'pi pi-sun',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'briefing.read', 'value' => false],
        ],
    ],
    // rnc-4 F5a (R30–R33): the firm's own settings — contact, hours, credentials line, the
    // specialists' hours and the consent's versions. The manager's (`gestao`); never the look nor
    // the modules, which stay the operator's console.
    [
        'id' => 'firm',
        'nameKey' => 'rolesAndPermissions.groups.firm',
        'icon' => 'pi pi-building',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'firm.settings', 'value' => false],
        ],
    ],

    [
        'id' => 'platform',
        'nameKey' => 'rolesAndPermissions.groups.platform',
        'icon' => 'pi pi-server',
        'permissions' => [
            ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'platform.firms', 'value' => false],
            ['labelKey' => 'rolesAndPermissions.permissions.consent', 'key' => 'platform.consent', 'value' => false],
        ],
    ],
];
