// Shared module metadata for the extraction pipeline. Maps the codebase's
// modules to the docs' functional areas, in a stable display order.
//
// ⚠️ EXAMPLE CONTENT — this is the docs.trainingbeta map, kept as a worked
// example of the shape. REPLACE every entry below with YOUR project's modules,
// table ownership, and PHP/Vue module → docs-area maps before running the
// extractors. The keys/values here will not match your codebase.

export const MODULES = [
  { id: 'auth', label: 'Users & Permissions', status: 'live' },
  { id: 'content', label: 'Program Content', status: 'live' },
  { id: 'builder', label: 'Workout Builder (staff)', status: 'live' },
  { id: 'training', label: 'Training & Enrollment', status: 'live' },
  { id: 'logbook', label: 'Logbook & Completions', status: 'live' },
  { id: 'migration', label: 'Legacy Migration', status: 'live' },
  { id: 'support', label: 'Support', status: 'live' },
  { id: 'infra', label: 'Infrastructure', status: 'live' },
];

export const MODULE_LABEL = Object.fromEntries(MODULES.map((m) => [m.id, m.label]));
export const MODULE_STATUS = Object.fromEntries(MODULES.map((m) => [m.id, m.status]));

// What a table with no owner below is filed under, so the extraction can refuse it.
export const UNMAPPED_MODULE = 'unmapped';

// Table → docs module. Migrations live in one flat folder, so ownership is
// curated here. A table missing from this map fails the extraction.
export const TABLE_MODULE = {
  // auth
  users: 'auth',
  roles: 'auth',
  role_permissions: 'auth',
  user_permissions: 'auth',
  personal_access_tokens: 'auth',
  // program content (the workout structure authored in the Builder)
  programs: 'content',
  cycles: 'content',
  program_cycle: 'content',
  cycle_day: 'content',
  days: 'content',
  day_round: 'content',
  rounds: 'content',
  round_exercise: 'content',
  round_superset: 'content',
  supersets: 'content',
  superset_exercise: 'content',
  exercises: 'content',
  // performance-variable lookups
  body_positions: 'content',
  grip_types: 'content',
  grades: 'content',
  edge_size_presets: 'content',
  // training / enrollment
  user_programs: 'training',
  user_entitlements: 'training',
  // logbook & completions
  exercise_completions: 'logbook',
  program_item_completions: 'logbook',
  // legacy migration
  legacy_id_map: 'migration',
  migration_audit: 'migration',
  migration_runs: 'migration',
  // infrastructure
  cache: 'infra',
  cache_locks: 'infra',
  jobs: 'infra',
  job_batches: 'infra',
  failed_jobs: 'infra',
  sessions: 'infra',
  password_reset_tokens: 'infra',
  migrations: 'infra',
};

// PHP module namespace (App\Modules\<X>) → docs module id (for endpoints).
export const PHP_MODULE_TO_DOCS = {
  Auth: 'auth',
  Builder: 'builder',
  Programs: 'training',
  Logbook: 'logbook',
  OfficeHours: 'support',
  Faq: 'support',
};

// Vue module folder (app.trainingbeta/src/modules/<X>) → docs module id.
export const VUE_MODULE_TO_DOCS = {
  Auth: 'auth',
  User: 'auth',
  Program: 'training',
  Cycle: 'content',
  Day: 'content',
  Exercise: 'content',
  Logbook: 'logbook',
  Builder: 'builder',
  OfficeHours: 'support',
  Faq: 'support',
};

export function moduleForTable(table) {
  return TABLE_MODULE[table] ?? UNMAPPED_MODULE;
}

/**
 * Everything the extraction found that the portal would not show: an endpoint or view whose module
 * is not in MODULES (the reference pages render per module, so it silently vanished), and a table
 * with no owner in TABLE_MODULE (it used to fall into Infrastructure).
 */
export function findUnmapped({ tables = {}, endpoints = [], views = [] }) {
  const known = new Set(MODULES.map((m) => m.id));
  const problems = [];

  for (const table of Object.values(tables)) {
    if (table.module === UNMAPPED_MODULE) problems.push(`table ${table.name}: no owner in TABLE_MODULE`);
    else if (!known.has(table.module)) problems.push(`table ${table.name}: module '${table.module}' is not in MODULES`);
  }
  for (const endpoint of endpoints) {
    if (!known.has(endpoint.module)) {
      problems.push(`endpoint ${endpoint.verb} ${endpoint.path}: module '${endpoint.module}' is not in MODULES`);
    }
  }
  for (const view of views) {
    if (!known.has(view.module)) problems.push(`view ${view.routeName}: module '${view.module}' is not in MODULES`);
  }

  return problems;
}
