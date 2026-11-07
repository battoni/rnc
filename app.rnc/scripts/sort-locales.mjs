import { readFileSync, writeFileSync } from 'fs';
import { resolve } from 'path';

/**
 * Top-level keys grouped by category.
 * COMMON → COMPONENT → MODULE, alphabetical within each group.
 * Add new top-level keys here when creating new locale sections.
 */
const TOP_LEVEL_GROUPS = {
  common: 'common',
  errors: 'common',
  address: 'component', // MAddressFields, MLocalityFields
  imageUpload: 'component', // MImageUpload
  navigation: 'component', // TheNavbar
  theme: 'component', // AColorSchemeToggle
  auth: 'module',
  home: 'module', // modules/Home (rnc-1)
  finance: 'module',
  notFound: 'module', // views/NotFound
  personalization: 'module',
  rolesAndPermissions: 'module',
  site: 'module', // modules/Site (rnc-1)
  users: 'module',
};

/*
 * An unregistered key still sorts — as a module — but silently: a component's section lands among
 * the modules and nobody is told. Say so, so the next key gets registered when it is added.
 */
function warnUnregistered(obj, file) {
  const unregistered = Object.keys(obj).filter((key) => !(key in TOP_LEVEL_GROUPS));

  if (unregistered.length === 0) return;

  console.warn(`! ${file}: not in TOP_LEVEL_GROUPS, sorted as module: ${unregistered.join(', ')}`);
}

function sortObj(obj) {
  const oneLiners = [];
  const multiLiners = [];

  for (const [key, value] of Object.entries(obj)) {
    if (typeof value === 'string') oneLiners.push(key);
    else multiLiners.push(key);
  }

  oneLiners.sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
  multiLiners.sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));

  const result = {};
  for (const key of [...oneLiners, ...multiLiners]) {
    result[key] = typeof obj[key] === 'object' ? sortObj(obj[key]) : obj[key];
  }
  return result;
}

function sortTopLevel(obj) {
  const groups = { common: [], component: [], module: [] };

  for (const key of Object.keys(obj)) {
    const group = TOP_LEVEL_GROUPS[key] ?? 'module';
    groups[group].push(key);
  }

  for (const group of Object.values(groups)) {
    group.sort((a, b) => a.localeCompare(b, undefined, { sensitivity: 'base' }));
  }

  const result = {};
  for (const key of [...groups.common, ...groups.component, ...groups.module]) {
    result[key] = sortObj(obj[key]);
  }
  return result;
}

const localesDir = resolve(process.cwd(), 'src/locales');
const files = ['en.json', 'pt-BR.json'];

for (const file of files) {
  const filePath = resolve(localesDir, file);
  const raw = JSON.parse(readFileSync(filePath, 'utf8'));
  warnUnregistered(raw, file);
  const sorted = sortTopLevel(raw);
  writeFileSync(filePath, JSON.stringify(sorted, null, 2) + '\n');
  console.log(`✓ ${file}`);
}
