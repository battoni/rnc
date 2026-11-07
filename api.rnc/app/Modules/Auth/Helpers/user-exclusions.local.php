<?php

/*
 * Project-owned: permission keys the seeded member (`user`) role must NOT receive — the cortex sync
 * NEVER touches this file (classified LOCAL).
 *
 * `ManagerSeeder` hands the member every key of every group, minus the core exclusions it holds
 * itself (the staff directory, moderation, the permission matrix). A group a project adds in
 * `permission-groups.local.php` reaches the member whole — create and delete included — so a
 * project whose members should only read it lists the write keys here instead of editing the
 * seeder, which is synced and would lose the edit on the next update. The keys are merged into the
 * core list; they can only take keys away, never grant one. Example:
 *
 *   return [
 *       'locations.create',
 *       'locations.delete',
 *   ];
 */

return [];
