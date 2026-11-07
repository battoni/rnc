// Extraction orchestrator. Runs the schema/endpoints/views extractors over the
// sibling sub-projects (read-only) and writes deterministic JSON to
// .vitepress/data/. With `--check` it regenerates in memory and fails if the
// committed JSON is stale (freshness CI).
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { extractSchema } from './extract-schema.mjs';
import { extractEndpoints } from './extract-endpoints.mjs';
import { extractViews } from './extract-views.mjs';
import { findUnmapped, MODULES } from './lib/modules.mjs';
import { PLANNED_ENDPOINTS, PLANNED_TABLES, PLANNED_VIEWS } from './planned-seeds.mjs';

const DATA_DIR = new URL('../.vitepress/data/', import.meta.url);
const CHECK = process.argv.includes('--check');

async function buildSchema() {
  const tables = await extractSchema();

  // A planned table only where the real one does not exist yet.
  for (const [name, table] of Object.entries(PLANNED_TABLES)) {
    if (!tables[name]) tables[name] = { name, ...table, status: 'planned' };
  }

  // Deterministic: tables sorted by name.
  const sorted = {};
  for (const name of Object.keys(tables).sort()) sorted[name] = tables[name];

  const modules = MODULES.map((m) => ({
    ...m,
    tables: Object.keys(sorted)
      .filter((t) => sorted[t].module === m.id)
      .sort(),
  })).filter((m) => m.tables.length);

  return { generatedFrom: '{{API_DIR}}/database/migrations', modules, tables: sorted };
}

async function buildEndpoints() {
  const all = await extractEndpoints();

  for (const planned of PLANNED_ENDPOINTS) {
    const isBuilt = all.some((endpoint) => endpoint.verb === planned.verb && endpoint.path === planned.path);
    if (!isBuilt) all.push({ ...planned, status: 'planned' });
  }
  all.sort(
    (a, b) =>
      a.module.localeCompare(b.module) || a.path.localeCompare(b.path) || a.verb.localeCompare(b.verb),
  );
  const modules = MODULES.map((m) => ({
    id: m.id,
    label: m.label,
    status: m.status,
    count: all.filter((e) => e.module === m.id).length,
  })).filter((m) => m.count);
  return { generatedFrom: '{{API_DIR}}/routes/api.php (+ api.local.php)', modules, endpoints: all };
}

async function buildViews() {
  const { views, navItems, permissionGroups, reachability } = await extractViews();

  const liveRouteNames = new Set(views.map((view) => view.routeName));
  for (const planned of PLANNED_VIEWS) {
    if (liveRouteNames.has(planned.routeName)) continue;
    reachability.push({
      view: planned.component,
      routeName: planned.routeName,
      reachedVia: planned.reachedVia,
      gate: planned.gate ?? null,
      status: 'planned',
    });
  }

  return {
    generatedFrom:
      '{{APP_DIR}}/src/modules/*/views/*/routes.ts + TheNavbar.vue (+ navItems.local.ts) + PermissionGroupsHelper.php (+ permission-groups.local.php)',
    views,
    navItems,
    permissionGroups,
    reachability,
  };
}

function serialize(obj) {
  return JSON.stringify(obj, null, 2) + '\n';
}

async function run() {
  await mkdir(DATA_DIR, { recursive: true });

  const targets = {
    'schema.json': await buildSchema(),
    'endpoints.json': await buildEndpoints(),
    'views.json': await buildViews(),
  };

  // A module the portal does not know renders nowhere, and a table with no owner lands in the wrong
  // diagram — both used to pass silently. Refuse before writing anything.
  const unmapped = findUnmapped({
    tables: targets['schema.json'].tables,
    endpoints: targets['endpoints.json'].endpoints,
    views: [...targets['views.json'].views, ...PLANNED_VIEWS],
  });
  if (unmapped.length) {
    console.error('✗ scripts/lib/modules.mjs does not cover what the code declares:');
    for (const problem of unmapped) console.error(`  - ${problem}`);
    process.exit(1);
  }

  let stale = false;
  for (const [file, data] of Object.entries(targets)) {
    const next = serialize(data);
    const url = new URL(file, DATA_DIR);
    if (CHECK) {
      let current = '';
      try {
        current = await readFile(url, 'utf8');
      } catch {
        current = '';
      }
      if (current !== next) {
        stale = true;
        console.error(`✗ ${file} is stale — run \`npm run docs:extract\` and commit the result.`);
      } else {
        console.log(`✓ ${file} fresh`);
      }
    } else {
      await writeFile(url, next);
      console.log(`✓ wrote ${file}`);
    }
  }

  if (CHECK && stale) process.exit(1);
}

run().catch((err) => {
  console.error(err);
  process.exit(1);
});
