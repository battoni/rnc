// Extracts API endpoints (verb, path, controller@action, permission, auth) from
// {{API_DIR}}/routes/api.php by static parsing. Handles literal routes,
// nested `Route::prefix(...)->group(...)` and `Route::middleware('auth')->group(...)`
// blocks, and `Route::apiResource(...)` expansion. Read-only.
import { readFile } from 'node:fs/promises';
import { PHP_MODULE_TO_DOCS, MODULE_STATUS } from './lib/modules.mjs';
import { stripPhpComments } from './lib/strip-php-comments.mjs';

// api.php holds the bootstrap's routes; api.local.php, when the project has one, its own features.
const ROUTES_FILES = [
  new URL('../../{{API_DIR}}/routes/api.php', import.meta.url),
  new URL('../../{{API_DIR}}/routes/api.local.php', import.meta.url),
];

const VERBS = '(get|post|put|patch|delete)';

/**
 * `$rolesRead = ['auth', 'permission:permissions.read,users.create'];` →
 * `{ rolesRead: ['auth', 'permission:permissions.read,users.create'] }`.
 * String-only arrays; an associative map (`'slug' => Controller::class`) is skipped.
 */
export function parseMiddlewareAliases(src) {
  const aliases = {};
  const re = /\$(\w+)\s*=\s*\[([^\]]*)\];/g;
  let m;
  while ((m = re.exec(src))) {
    const [, name, body] = m;
    if (body.includes('=>')) continue;
    const parts = [...body.matchAll(/['"]([^'"]+)['"]/g)].map((part) => part[1]);
    if (parts.length) aliases[name] = parts;
  }
  return aliases;
}

/**
 * Expand `->middleware($var)` into the strings it holds, so the auth and permission matchers see
 * them. A route guarded through a variable used to be documented as open to everyone.
 */
export function resolveMiddlewareTail(tail, aliases) {
  return tail.replace(/middleware\(\s*\$(\w+)\s*\)/g, (full, name) => {
    const resolved = aliases[name];
    if (!resolved) return full;
    return `middleware([${resolved.map((part) => `'${part}'`).join(', ')}])`;
  });
}

/** `permission:a,b` lists every key the middleware accepts, not only the first. */
function permissionFrom(tail) {
  return tail.match(/permission:([a-z0-9_.,]+)/i)?.[1] ?? null;
}

/** Whether a middleware list (`'auth'` or `['auth', …]`) applies `auth`. */
function appliesAuth(tail) {
  return /middleware\(\s*\[?[^)]*['"]auth['"]/.test(tail);
}

// Bracket-match every `Route::...->group(function (...): void { <body> })` and record
// its prefix, whether it applies the 'auth' middleware and the permission it requires.
// Nested groups are captured too because exec() keeps scanning past the opening brace.
function findGroups(src) {
  const groups = [];
  // The closure's return type (`: void`) is optional — without it in the pattern, no group in a
  // typed routes file was found, and every route inside one was documented as public.
  const re = /Route::([^;]*?)->group\(\s*(?:static\s+)?function\s*\([^)]*\)\s*(?::\s*\??[\w\\]+\s*)?\{/g;
  let m;
  while ((m = re.exec(src))) {
    const head = m[1];
    const prefix = head.match(/prefix\(\s*['"]([^'"]+)['"]\s*\)/)?.[1] ?? null;
    const auth = appliesAuth(head);
    const permission = permissionFrom(head);
    let depth = 1;
    let i = re.lastIndex;
    while (i < src.length && depth > 0) {
      const ch = src[i];
      if (ch === '{') depth++;
      else if (ch === '}') depth--;
      i++;
    }
    groups.push({ prefix, auth, permission, start: re.lastIndex, end: i });
  }
  return groups;
}

function groupContext(groups, idx) {
  const containing = groups.filter((g) => idx >= g.start && idx < g.end).sort((a, b) => a.start - b.start);
  return {
    prefix: containing.map((g) => g.prefix).filter(Boolean).join('/'),
    auth: containing.some((g) => g.auth),
    permissions: containing.map((g) => g.permission).filter(Boolean),
  };
}

// A group's `permission:` middleware and the route's own both run, so the route needs every one of
// them: the layers join with ` + ` (all of), while a comma inside one layer still means any of.
function joinPermissions(layers) {
  const present = layers.filter(Boolean);
  return present.length ? present.join(' + ') : null;
}

function joinPath(prefix, path) {
  const cleaned = path.replace(/^\//, '').replace(/\/$/, '');
  return [prefix, cleaned].filter(Boolean).join('/');
}

function makeEndpoint(verb, fullPath, controller, action, controllerModule, { permission = null, auth = false } = {}) {
  const module = controllerModule[controller] ?? 'other';
  return {
    verb: verb.toUpperCase(),
    path: `/${fullPath}`,
    controller,
    action,
    module,
    permission,
    auth: auth || !!permission,
    status: MODULE_STATUS[module] ?? 'live',
  };
}

const REST = [
  { verb: 'get', suffix: '', action: 'index' },
  { verb: 'post', suffix: '', action: 'store' },
  { verb: 'get', suffix: '/{id}', action: 'show' },
  { verb: 'put', suffix: '/{id}', action: 'update' },
  { verb: 'delete', suffix: '/{id}', action: 'destroy' },
];

export async function extractEndpoints() {
  const parts = [];
  for (const file of ROUTES_FILES) {
    try {
      parts.push(await readFile(file, 'utf8'));
    } catch {
      // api.local.php is optional
    }
  }
  return parseEndpoints(parts.join('\n'));
}

/** Every endpoint the given routes source declares. Comments are removed first: a route in one is not a route. */
export function parseEndpoints(routesSource) {
  const rawSrc = stripPhpComments(routesSource);
  const aliases = parseMiddlewareAliases(rawSrc);
  const src = resolveMiddlewareTail(rawSrc, aliases);

  // Controller class -> docs module, from `use App\Modules\<X>\Controllers\<C>;`
  // (the trailing `as Alias` is handled too).
  const controllerModule = {};
  const useRe = /use\s+App\\Modules\\([A-Za-z]+)\\Controllers\\([A-Za-z]+)(?:\s+as\s+([A-Za-z]+))?;/g;
  let u;
  while ((u = useRe.exec(src))) {
    const module = PHP_MODULE_TO_DOCS[u[1]] ?? u[1].toLowerCase();
    controllerModule[u[2]] = module;
    if (u[3]) controllerModule[u[3]] = module;
  }

  const groups = findGroups(src);
  const endpoints = [];

  // 1) Literal routes: Route::verb('path', [Controller::class, 'action'])->...;
  const litRe = new RegExp(
    `Route::${VERBS}\\(\\s*['"]([^'"]+)['"]\\s*,\\s*\\[\\s*([A-Za-z]+)::class\\s*,\\s*['"]([^'"]+)['"]\\s*\\]\\s*\\)([^;]*);`,
    'g',
  );
  let r;
  while ((r = litRe.exec(src))) {
    const [, verb, path, controller, action, rawTail] = r;
    const tail = resolveMiddlewareTail(rawTail, aliases);
    const ctx = groupContext(groups, r.index);
    const permission = joinPermissions([...ctx.permissions, permissionFrom(tail)]);
    endpoints.push(
      makeEndpoint(verb, joinPath(ctx.prefix, path), controller, action, controllerModule, {
        permission,
        auth: ctx.auth || appliesAuth(tail),
      }),
    );
  }

  // 2) Route::apiResource('name', Controller::class)->except([...]) / ->only([...])
  const resRe = /Route::apiResource\(\s*['"]([^'"]+)['"]\s*,\s*([A-Za-z]+)::class\s*\)([^;]*);/g;
  let a;
  while ((a = resRe.exec(src))) {
    const [, name, controller, tail] = a;
    const except = [...(tail.match(/except\(\[([^\]]*)\]/)?.[1] ?? '').matchAll(/'([a-z]+)'/g)].map((e) => e[1]);
    const onlyRaw = tail.match(/only\(\[([^\]]*)\]/)?.[1];
    const only = onlyRaw ? [...onlyRaw.matchAll(/'([a-z]+)'/g)].map((e) => e[1]) : null;
    const ctx = groupContext(groups, a.index);

    for (const rest of REST) {
      if (only ? !only.includes(rest.action) : except.includes(rest.action)) continue;
      endpoints.push(
        makeEndpoint(rest.verb, joinPath(ctx.prefix, `${name}${rest.suffix}`), controller, rest.action, controllerModule, {
          permission: joinPermissions([...ctx.permissions, permissionFrom(tail)]),
          auth: ctx.auth || appliesAuth(tail),
        }),
      );
    }
  }

  return endpoints;
}
