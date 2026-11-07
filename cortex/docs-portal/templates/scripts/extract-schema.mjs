// Extracts the DB schema (tables, columns, types, nullable, defaults, FKs,
// indexes) from {{API_DIR}}/database/migrations/*.php by static parsing.
// Read-only; never imports PHP or touches a database.
import { readdir, readFile } from 'node:fs/promises';
import { moduleForTable, MODULE_STATUS } from './lib/modules.mjs';

const MIGRATIONS_DIR = new URL('../../{{API_DIR}}/database/migrations/', import.meta.url);

// Blueprint macros the project registers (e.g. in AppServiceProvider) stamp a set of columns that
// static parsing cannot see, because it does not run PHP. Mirror each macro's columns here and
// keep the two in step — an unmirrored macro documents a table without those columns, silently.
//   myMacro: [{ name: 'street', type: 'varchar', nullable: false }, …]
//
// Brazil — the two below mirror `Helpers/Brazil/AddressColumns.php`. Delete them together with
// `Helpers/Brazil/` when the product is not Brazilian.
const COORDINATE = 'decimal(10,7)';
export const MACRO_COLUMNS = {
  addressColumns: [
    { name: 'cep', type: 'varchar', nullable: true },
    { name: 'street', type: 'varchar', nullable: false },
    { name: 'number', type: 'varchar', nullable: false },
    { name: 'complement', type: 'varchar', nullable: true },
    { name: 'district', type: 'varchar', nullable: false },
    { name: 'city', type: 'varchar', nullable: false },
    { name: 'state', type: 'varchar(2)', nullable: false },
    { name: 'lat', type: COORDINATE, nullable: true },
    { name: 'lng', type: COORDINATE, nullable: true },
  ],
  localityColumns: [
    { name: 'state', type: 'varchar(2)', nullable: false },
    { name: 'city', type: 'varchar', nullable: false },
    { name: 'cep', type: 'varchar', nullable: true },
    { name: 'region', type: 'varchar', nullable: true },
    { name: 'lat', type: COORDINATE, nullable: true },
    { name: 'lng', type: COORDINATE, nullable: true },
  ],
};

/** Map a Blueprint column method to a display type. */
function displayType(method, args) {
  const a = args.map((s) => s.trim());
  switch (method) {
    case 'id':
    case 'bigIncrements':
      return 'bigint';
    case 'foreignId':
    case 'unsignedBigInteger':
      return 'bigint';
    case 'string':
      return a[1] ? `varchar(${a[1]})` : 'varchar';
    case 'char':
      return a[1] ? `char(${a[1]})` : 'char';
    case 'text':
    case 'mediumText':
    case 'longText':
      return 'text';
    case 'boolean':
      return 'boolean';
    case 'integer':
    case 'unsignedInteger':
      return 'integer';
    case 'tinyInteger':
    case 'unsignedTinyInteger':
      return 'tinyint';
    case 'decimal':
    case 'unsignedDecimal':
      return a[1] ? `decimal(${a[1]},${a[2] ?? 0})` : 'decimal';
    case 'float':
    case 'double':
      return 'double';
    case 'json':
    case 'jsonb':
      return 'json';
    case 'date':
      return 'date';
    case 'dateTime':
    case 'timestamp':
      return 'timestamp';
    case 'time':
      return 'time';
    case 'uuid':
      return 'uuid';
    case 'enum':
      return `enum(${(a.slice(1).join(', ') || '').replace(/[[\]']/g, '')})`;
    default:
      return method;
  }
}

// Split a "$table->...;" statement list while respecting brackets/quotes.
function splitStatements(body) {
  const out = [];
  let buf = '';
  let depth = 0;
  let quote = null;
  for (const ch of body) {
    if (quote) {
      if (ch === quote) quote = null;
      buf += ch;
      continue;
    }
    if (ch === "'" || ch === '"') {
      quote = ch;
      buf += ch;
      continue;
    }
    if (ch === '(' || ch === '[') depth++;
    if (ch === ')' || ch === ']') depth--;
    if (ch === ';' && depth === 0) {
      out.push(buf.trim());
      buf = '';
      continue;
    }
    buf += ch;
  }
  if (buf.trim()) out.push(buf.trim());
  return out;
}

// Parse the arguments of the FIRST `->method(...)` call in a statement.
function firstCallArgs(statement) {
  const m = statement.match(/->\s*([a-zA-Z]+)\s*\(([^)]*)\)/);
  if (!m) return null;
  const args = m[2].trim()
    ? m[2].split(',').map((s) => s.trim().replace(/^['"]|['"]$/g, ''))
    : [];
  return { method: m[1], rawArgs: m[2], args };
}

export function parseColumns(table, body) {
  const columns = [];
  const indexes = [];

  // Comment lines out first: a comment between two statements glues itself to the next one, and
  // `startsWith('$table->')` then skips a real column — that is how a foreign key vanished.
  const cleaned = body.replace(/^\s*\/\/.*$/gm, '');

  for (const stmt of splitStatements(cleaned)) {
    if (!stmt.startsWith('$table->')) continue;
    const first = firstCallArgs(stmt);
    if (!first) continue;
    const { method, args } = first;

    if (MACRO_COLUMNS[method]) {
      for (const col of MACRO_COLUMNS[method]) {
        columns.push({ ...col, default: null, fk: null, pk: false });
      }
      continue;
    }

    // Structural helpers.
    if (method === 'id') {
      columns.push({ name: args[0] || 'id', type: 'bigint', nullable: false, default: null, fk: null, pk: true });
      continue;
    }
    if (method === 'timestamps') {
      columns.push({ name: 'created_at', type: 'timestamp', nullable: true, default: null, fk: null, pk: false });
      columns.push({ name: 'updated_at', type: 'timestamp', nullable: true, default: null, fk: null, pk: false });
      continue;
    }
    if (method === 'softDeletes') {
      columns.push({ name: args[0] || 'deleted_at', type: 'timestamp', nullable: true, default: null, fk: null, pk: false });
      continue;
    }
    if (method === 'rememberToken') {
      columns.push({ name: 'remember_token', type: 'varchar(100)', nullable: true, default: null, fk: null, pk: false });
      continue;
    }
    if (method === 'unique' || method === 'index' || method === 'primary') {
      const cols = (stmt.match(/\[([^\]]*)\]/)?.[1] || args.join(','))
        .split(',')
        .map((s) => s.trim().replace(/^['"]|['"]$/g, ''))
        .filter(Boolean);
      if (cols.length) indexes.push({ columns: cols, unique: method === 'unique' });
      continue;
    }
    if (method === 'foreign' || method === 'foreignIdFor') continue; // rare; skip
    if (method.startsWith('drop')) continue; // dropColumn / dropIndex are not columns

    const name = args[0];
    if (!name) continue;

    const nullable = /->\s*nullable\s*\(/.test(stmt);
    const defaultMatch = stmt.match(/->\s*default\s*\(([^)]*)\)/);
    let def = defaultMatch ? defaultMatch[1].trim().replace(/^['"]|['"]$/g, '') : null;
    if (def === '') def = null;

    let fk = null;
    if (method === 'foreignId') {
      const constrained = stmt.match(/->\s*constrained\s*\(\s*['"]?([a-z_]+)?['"]?\s*\)/);
      const fkTable = constrained?.[1] || guessFkTable(name);
      fk = fkTable ? `${fkTable}.id` : null;
    }

    columns.push({
      name,
      type: displayType(method, args),
      nullable,
      default: def,
      fk,
      pk: false,
    });
  }

  return { columns, indexes };
}

/** The body between a balanced `{` at `start` and its closing `}`. */
function balancedBody(src, start) {
  let depth = 1;
  let i = start;
  while (i < src.length && depth > 0) {
    if (src[i] === '{') depth++;
    else if (src[i] === '}') depth--;
    i++;
  }
  return src.slice(start, i - 1);
}

// A migration's up() only. down() reverts what up() did — a dropColumn in an add-migration's
// down() would otherwise remove from the document the very column up() just added.
function extractUpBody(src) {
  const match = /function\s+up\s*\(\s*\)\s*:?\s*\w*\s*\{/.exec(src);
  return match ? balancedBody(src, match.index + match[0].length) : src;
}

// Column names removed by `$table->dropColumn('x')` or `dropColumn(['x', 'y'])`.
function droppedColumns(body) {
  return [...body.matchAll(/->\s*dropColumn\s*\(\s*(\[[^\]]*\]|['"][^'"]+['"])\s*\)/g)].flatMap((match) => {
    const inner = match[1];
    if (inner.startsWith('[')) return [...inner.matchAll(/['"]([^'"]+)['"]/g)].map((column) => column[1]);
    return [inner.replace(/^['"]|['"]$/g, '')];
  });
}

// machine_id -> machines (Laravel convention) when ->constrained() has no arg.
function guessFkTable(column) {
  if (!column.endsWith('_id')) return null;
  const base = column.slice(0, -3);
  return base.endsWith('s') ? base : `${base}s`;
}

export async function extractSchema() {
  const files = (await readdir(MIGRATIONS_DIR)).filter((f) => f.endsWith('.php')).sort();
  const tables = {};

  for (const file of files) {
    const src = await readFile(new URL(file, MIGRATIONS_DIR), 'utf8');
    // Each `Schema::create('table', function (...) { <body> });`
    const re = /Schema::create\(\s*['"]([a-z_]+)['"]\s*,\s*function\s*\([^)]*\)\s*\{/g;
    let m;
    while ((m = re.exec(src))) {
      const table = m[1];
      // Capture the body by bracket-matching from the opening `{`.
      const bodyStart = re.lastIndex;
      let depth = 1;
      let i = bodyStart;
      while (i < src.length && depth > 0) {
        const ch = src[i];
        if (ch === '{') depth++;
        else if (ch === '}') depth--;
        i++;
      }
      const body = src.slice(bodyStart, i - 1);
      const { columns, indexes } = parseColumns(table, body);
      const module = moduleForTable(table);
      tables[table] = { name: table, module, status: MODULE_STATUS[module] ?? 'live', columns, indexes };
    }
  }

  // Second pass: `Schema::table('x', …)` alters, read from up() only. A bootstrap folds new columns
  // into the create (arcus-api-architecture §3), but a project with production data cannot, and
  // its portal must document the table the alters produce: new columns merged, dropped columns
  // removed, new indexes added once.
  for (const file of files) {
    const src = extractUpBody(await readFile(new URL(file, MIGRATIONS_DIR), 'utf8'));
    for (const match of src.matchAll(/Schema::table\(\s*['"]([a-z_]+)['"]\s*,\s*function\s*\([^)]*\)\s*\{/g)) {
      const table = match[1];
      if (!tables[table]) continue;

      const body = balancedBody(src, match.index + match[0].length);
      const { columns, indexes } = parseColumns(table, body);

      const have = new Set(tables[table].columns.map((column) => column.name));
      for (const column of columns) {
        if (have.has(column.name)) continue;
        tables[table].columns.push(column);
        have.add(column.name);
      }

      const dropped = droppedColumns(body);
      if (dropped.length) {
        tables[table].columns = tables[table].columns.filter((column) => !dropped.includes(column.name));
      }

      for (const index of indexes) {
        const key = `${index.unique}:${index.columns.join(',')}`;
        const exists = tables[table].indexes.some((existing) => `${existing.unique}:${existing.columns.join(',')}` === key);
        if (!exists) tables[table].indexes.push(index);
      }
    }
  }

  return tables;
}
