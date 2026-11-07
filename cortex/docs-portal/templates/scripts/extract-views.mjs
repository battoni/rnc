// Extracts the app's views and nav items from
// {{APP_DIR}}/src/modules/*/views/<View>/routes.ts + TheNavbar.vue, and the
// permission groups from PermissionGroupsHelper.php. Read-only static parsing.
//
// Every source is read together with its `.local.` twin when the project has one: the cortex sync
// keeps the core file and the project's extension apart, and a portal that reads only the core
// documents a product without its own features.
import { readdir, readFile, stat } from 'node:fs/promises';
import { VUE_MODULE_TO_DOCS } from './lib/modules.mjs';

const MODULES_DIR = new URL('../../{{APP_DIR}}/src/modules/', import.meta.url);
const NAVBAR_FILES = [
  new URL('../../{{APP_DIR}}/src/components/unique/TheNavbar/TheNavbar.vue', import.meta.url),
  new URL('../../{{APP_DIR}}/src/components/unique/TheNavbar/navItems.local.ts', import.meta.url),
];
const PERM_FILES = [
  new URL('../../{{API_DIR}}/app/Modules/Auth/Helpers/PermissionGroupsHelper.php', import.meta.url),
  new URL('../../{{API_DIR}}/app/Modules/Auth/Helpers/permission-groups.local.php', import.meta.url),
];

/** Block and line comments out, so an example inside a doc-comment never parses as the real thing. */
function stripComments(src) {
  return src.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
}

async function readIfFile(url) {
  try {
    const s = await stat(url);
    if (!s.isFile()) return null;
    return await readFile(url, 'utf8');
  } catch {
    return null;
  }
}

async function extractRoutes() {
  const entries = await readdir(MODULES_DIR);
  const byName = new Map();

  for (const dir of entries) {
    const module = VUE_MODULE_TO_DOCS[dir] ?? dir.toLowerCase();

    // Routes are declared per view (modules/<X>/views/<View>/routes.ts), with a module-root
    // routes.ts as an optional second place. Reading only one of the two documented two views.
    const routeFiles = [new URL(`${dir}/routes.ts`, MODULES_DIR), new URL(`${dir}/views/routes.ts`, MODULES_DIR)];
    try {
      for (const view of await readdir(new URL(`${dir}/views/`, MODULES_DIR))) {
        routeFiles.push(new URL(`${dir}/views/${view}/routes.ts`, MODULES_DIR));
      }
    } catch {
      // a module without views/
    }

    for (const file of routeFiles) {
      const src = await readIfFile(file);
      if (!src) continue;

      // One route per `path:`, whatever order its keys come in and whatever sits between them
      // (`meta: { … }`, `props`, `beforeEnter`): the object runs until the next `path:`.
      const clean = stripComments(src);
      const starts = [...clean.matchAll(/path:\s*'([^']+)'/g)];
      starts.forEach((start, index) => {
        const chunk = clean.slice(start.index, starts[index + 1]?.index ?? clean.length);
        const name = chunk.match(/name:\s*'([^']+)'/)?.[1];
        const importPath = chunk.match(/component:\s*\(\)\s*=>\s*import\('([^']+)'\)/)?.[1];
        if (!name || !importPath) return;

        const component = importPath.split('/').pop().replace(/\.vue$/, '');
        if (!byName.has(name)) byName.set(name, { routeName: name, module, component, paths: [] });
        byName.get(name).paths.push(start[1]);
      });
    }
  }

  return [...byName.values()].sort((a, b) => a.routeName.localeCompare(b.routeName));
}

// Nav items are object literals — `{ icon, label, routeName, permission?, role? }` — declared as
// data, so staff-only is read off the item's own `role`, not guessed from where it sits in the
// file. A group (`children`) is not an item; its children are.
export function parseNavItems(src) {
  const items = [];
  for (const match of stripComments(src).matchAll(/\{[^{}]*?label:\s*'([^']+)'[^{}]*?\}/g)) {
    const literal = match[0];
    const routeName = literal.match(/routeName:\s*'([^']+)'/)?.[1];
    if (!routeName) continue;

    items.push({
      labelKey: match[1],
      target: routeName,
      permission: literal.match(/permission:\s*'([^']+)'/)?.[1] ?? null,
      // Any `role` restricts the item to one role — a slug string compared to `role_slug`, or a
      // project's own constant (`ROLES.ADMIN`). Matching one spelling left every item open.
      staff: /\brole:\s*[^\s,}]/.test(literal),
    });
  }
  return items;
}

async function extractNav() {
  const parts = [];
  for (const file of NAVBAR_FILES) {
    const src = await readIfFile(file);
    if (src) parts.push(src);
  }
  return parseNavItems(parts.join('\n'));
}

async function extractPermissionGroups() {
  const parts = [];
  for (const file of PERM_FILES) {
    const part = await readIfFile(file);
    if (part) parts.push(part);
  }
  const src = parts.join('\n');
  if (!src) return [];
  const groups = [];
  const groupRe = /'id'\s*=>\s*'([a-z_]+)'[\s\S]*?'permissions'\s*=>\s*\[([\s\S]*?)\]\s*,?\s*\]/g;
  let g;
  while ((g = groupRe.exec(src))) {
    const keys = [...g[2].matchAll(/'key'\s*=>\s*'([a-z0-9_.]+)'/g)].map((k) => k[1]);
    if (keys.length) groups.push({ id: g[1], permissions: keys });
  }
  return groups;
}

export async function extractViews() {
  const [views, navItems, permissionGroups] = await Promise.all([
    extractRoutes(),
    extractNav(),
    extractPermissionGroups(),
  ]);

  // Best-effort reachability: a view is "nav" if a nav item names its route, otherwise it is
  // reached from a hub, a parent or an auth flow. Route names, not paths: a path carries
  // params and localized spellings, a name is exact.
  const reachability = views.map((view) => {
    // A nav item names the route STEM (`users`); localized apps register one route per locale
    // (`users-en`, `users-pt-BR`). Compare stems, or every localized screen reads as orphaned.
    const stem = view.routeName.replace(/-[a-z]{2}(-[A-Z]{2})?$/, '');
    const nav = navItems.find((n) => n.target === view.routeName || n.target === stem);
    return {
      view: view.component,
      routeName: view.routeName,
      reachedVia: nav ? (nav.staff ? 'nav:staff' : 'nav') : 'hub/parent',
      gate: nav?.staff ? 'staff' : null,
      status: 'live',
    };
  });

  return { views, navItems, permissionGroups, reachability };
}
