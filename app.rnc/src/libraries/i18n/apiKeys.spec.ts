import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import i18n from './index';

const LOCALES = ['en', 'pt-BR'];

/*
 * The API names a permission group by i18n KEY, not by text: `permission-groups.local.php` sets
 * `nameKey => 'rolesAndPermissions.groups.locations'` and the Perfis screen translates it. A key
 * with no translation renders as itself, so the screen listed
 * "rolesAndPermissions.groups.locations" where a person expected "Locais" (largatudoevai,
 * Leonardo's QA on 2026-09-22 — four groups at once).
 *
 * `usedKeys.spec.ts` cannot see these: they are never written as `$t('…')` in the source. This reads
 * the project's own group file — the LOCAL half, where a project adds its groups — and asks the same
 * question of it. rnc ships that file as an empty stub, so here it guards nothing and
 * downstream it guards every group the project added.
 */
// Split so the line still fits once the sync rewrites `api.rnc` to a longer project name.
const HELPERS_DIR = '../../../../api.rnc/app/Modules/Auth/Helpers';
const GROUPS_FILE = resolve(__dirname, `${HELPERS_DIR}/permission-groups.local.php`);

const NAME_KEY = /'nameKey'\s*=>\s*'([^']+)'/g;

// HELPERS
function keysIn(source: string): string[] {
  // The file documents the shape in a docblock; an example key is not a declared group.
  const declarations = source.replace(/\/\*[\s\S]*?\*\//g, '');

  return [...declarations.matchAll(NAME_KEY)].map((match) => match[1] as string);
}

function declaredKeys(): string[] {
  return keysIn(readFileSync(GROUPS_FILE, 'utf8'));
}

describe('permission groups the API names by key', () => {
  it.each(LOCALES)('%s translates every group this project declares', (locale) => {
    const missing = declaredKeys().filter((key) => !i18n.global.te(key, locale));

    expect(missing).toEqual([]);
  });

  it('reads a declared key and ignores the documented example', () => {
    const FILE = `<?php
/*
 *           'nameKey' => 'rolesAndPermissions.groups.example',
 */
return [
    ['id' => 'locations', 'nameKey' => 'rolesAndPermissions.groups.locations'],
];`;

    expect(keysIn(FILE)).toEqual(['rolesAndPermissions.groups.locations']);
  });
});
