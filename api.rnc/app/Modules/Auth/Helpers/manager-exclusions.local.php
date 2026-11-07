<?php

/*
 * Project-owned: permission keys the seeded Manager role must NOT receive — the cortex sync NEVER
 * touches this file (classified LOCAL).
 *
 * `ManagerSeeder` hands the Manager every key of every group, minus the core exclusions it holds
 * itself (`design.read`). A project whose feature must stay with the administrator lists the key
 * here instead of editing the seeder, which is synced and would lose the edit on the next update.
 * The keys are merged into the core list; they can only take keys away, never grant one.
 * Example:
 *
 *   return [
 *       'billing.refund',
 *   ];
 */

return [];
