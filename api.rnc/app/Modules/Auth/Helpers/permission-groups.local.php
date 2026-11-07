<?php

/*
 * Project-owned permission groups — the cortex sync NEVER touches this file (classified LOCAL).
 *
 * Return an array of additional permission-group structures; they are appended to the core
 * groups in PermissionGroupsHelper, so feature permissions survive every rnc update.
 * Example:
 *
 *   return [
 *       [
 *           'id' => 'catalog',
 *           'nameKey' => 'rolesAndPermissions.groups.catalog',
 *           'icon' => 'pi pi-folder',
 *           'permissions' => [
 *               ['labelKey' => 'rolesAndPermissions.permissions.read', 'key' => 'catalog.read', 'value' => false],
 *               ['labelKey' => 'rolesAndPermissions.permissions.manage', 'key' => 'catalog.manage', 'value' => false],
 *           ],
 *       ],
 *   ];
 */

return [];
