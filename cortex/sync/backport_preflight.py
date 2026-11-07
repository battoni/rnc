#!/usr/bin/env python3
"""Step 2 of BACKPORT.md: what the module REFERENCES and does not contain.

A module is not self-contained. Its directory is a fraction of what it needs, and nothing
in the manifest, the metadata or the name announces the rest. The first back-port took
thirteen seams, almost all of them discovered by a red test, one at a time — and the worst
ones do not break a test at all: a slot the target does not have simply renders nothing.

This scans the module at the SOURCE, lists everything it reaches for outside itself, and
checks each item against the TARGET. It runs before copying, not after.

Usage:
  backport_preflight.py <project> <module-path> [<path> ...]

  backport_preflight.py lifeshifter \\
      api.lifeshifter/app/Modules/Idea \\
      app.lifeshifter/src/modules/Idea

Exits 1 if anything is missing in the target; 0 if everything the module needs is there.
"""
import json
import os
import re
import subprocess
import sys
import pathlib
from pathlib import Path

PENDULUM = Path(__file__).resolve().parents[2]
# The root the children live under. In a worktree — which is how the audit runs the
# pre-flight at the commit before a port — the parent is not in battoni.dev, and without
# this valve the children are simply not found.
ROOT = pathlib.Path(os.environ.get('BACKPORT_RAIZ') or PENDULUM.parent)

ARCUS, CELER = PENDULUM / 'arcus', PENDULUM / 'celer'

# A `use` pointing at one of these is not a third-party dependency.
HOUSE_NAMESPACES = ('App\\', 'Database\\', 'Tests\\')
FRAMEWORK_NAMESPACES = ('Illuminate\\', 'Laravel\\')

# imports that are not npm packages
LOCAL_IMPORT = re.compile(r"^[./]|^@[A-Z]|^@/|^virtual:|^node:")

PRESENT, MISSING, UNSURE = '✓', '✗', '?'


class Report:
    def __init__(self):
        self.rows = []
        self.missing_total = 0

    def add(self, seam, item, state, note=''):
        self.rows.append((seam, item, state, note))
        if state == MISSING:
            self.missing_total += 1

    def render(self):
        current = None
        for seam, item, state, note in self.rows:
            if seam != current:
                print(f'\n═══ {seam} ═══')
                current = seam
            suffix = f'   {note}' if note else ''
            print(f'  {state} {item}{suffix}')

        print(f'\n{len(self.rows)} references · {self.missing_total} missing in the target')
        if not self.rows:
            print('  (nothing found — check the paths are right)')


def read_text_of(rel_path):
    try:
        return rel_path.read_text(encoding='utf-8')
    except (UnicodeDecodeError, OSError):
        return ''


def files_in(bases, suffixes):
    for base in bases:
        for rel_path in sorted(base.rglob('*')):
            if rel_path.is_file() and rel_path.suffix in suffixes:
                yield rel_path


def text_from(bases, suffixes):
    return '\n'.join(read_text_of(c) for c in files_in(bases, suffixes))


# ---------- the seams ----------

def _packages_by_namespace():
    """psr-4 prefix -> package, from the map Composer itself writes.

    A namespace is NOT a package name: `Carbon\\` comes from `nesbot/carbon`. Searching
    composer.json for the text "carbon/" was never going to match, and the pre-flight was
    asking for a dependency that was already installed. The prefix is not always one
    segment either — `Spatie\\LaravelData\\`, `Symfony\\Component\\...` — so the
    comparison runs over the whole namespace, longest prefix first.
    """
    try:
        data = json.loads(read_text_of(ARCUS / 'vendor/composer/installed.json') or '{}')
    except json.JSONDecodeError:
        return []

    packages = data.get('packages', data) if isinstance(data, dict) else data
    prefix_map = []
    for package in packages if isinstance(packages, list) else []:
        for prefix in ((package.get('autoload') or {}).get('psr-4') or {}):
            prefix_map.append((prefix, package.get('name', '?')))

    return sorted(prefix_map, key=lambda pair: -len(pair[0]))


def composer_dependencies(php, report):
    used = set()
    for line in re.findall(r'^use\s+([A-Za-z0-9_\\]+)', php, re.M):
        if line.startswith(HOUSE_NAMESPACES + FRAMEWORK_NAMESPACES):
            continue
        # No backslash means the global namespace — `RuntimeException`, `DateTime`,
        # `Throwable`. Those are PHP's, not a package's.
        if '\\' in line:
            used.add(line)

    if not used:
        return

    declared = read_text_of(ARCUS / 'composer.json')
    prefix_map = _packages_by_namespace()

    seen = {}
    for namespace in sorted(used):
        package = next((unit_name for prefix, unit_name in prefix_map if namespace.startswith(prefix)), None)
        root = namespace.split('\\')[0]
        if root in seen:
            continue
        seen[root] = True

        if not package:
            report.add('composer dependency', root, MISSING,
                          'no package installed in arcus provides this namespace')
            continue

        direta = f'"{package}"' in declared
        report.add('composer dependency', f'{root} → {package}',
                      PRESENT if direta else UNSURE,
                      '' if direta else 'reachable only transitively, not in composer.json — '
                                        'declare it if the module truly depends on it')


def npm_dependencies(ts, report):
    packages = set()
    for target_file in re.findall(r"from\s+'([^']+)'", ts):
        if LOCAL_IMPORT.match(target_file):
            continue
        packages.add(target_file.split('/')[0] if not target_file.startswith('@') else '/'.join(target_file.split('/')[:2]))

    if not packages:
        return

    manifest = read_text_of(CELER / 'package.json')
    for package in sorted(packages):
        is_there = f'"{package}"' in manifest
        report.add('npm dependency', package,
                      PRESENT if is_there else MISSING,
                      '' if is_there else 'not in celer package.json')


def where_alias_is_missing(alias):
    """The THREE files that have to know an alias, and which of them do not.

    `vite.config.ts` makes the build work, `tsconfig.app.json` makes the type-check work
    and `vitest.config.ts` makes the SUITE work. Forgetting one passes the other two: the
    module compiles, the editor is quiet, and the test dies in "Failed to resolve import".
    That is how the Tip port discovered that Idea — already merged, green three times —
    had the same hole, invisible while no Idea test went through the router.

    vitest declares the alias WITHOUT the `@` (`{ alias: 'TipModule' }`), so both
    spellings count.
    """
    short_form = alias.lstrip('@')
    vitest = read_text_of(CELER / 'vitest.config.ts')

    presence = (
        ('vite.config.ts', f"'{alias}'" in read_text_of(CELER / 'vite.config.ts')),
        ('tsconfig.app.json', f'"{alias}"' in read_text_of(CELER / 'tsconfig.app.json')),
        ('vitest.config.ts', f"'{alias}'" in vitest or f"'{short_form}'" in vitest),
    )

    return [unit_name for unit_name, ok in presence if not ok]


def aliases(ts, report):
    used = sorted(set(re.findall(r"from\s+'(@[A-Z][A-Za-z]*)'", ts)))

    for alias in used:
        lacking = where_alias_is_missing(alias)
        report.add('module alias', alias,
                      PRESENT if not lacking else MISSING,
                      '' if not lacking else 'missing from ' + ' and '.join(lacking))


def own_module_alias(bases, report):
    """The arriving module needs ITS OWN alias registered — in all three files."""
    for base in bases:
        parts = base.parts
        if 'modules' not in parts:
            continue

        unit_name = parts[parts.index('modules') + 1]
        alias = f'@{unit_name}Module'
        lacking = where_alias_is_missing(alias)

        report.add('alias of the arriving module', alias,
                      PRESENT if not lacking else MISSING,
                      '' if not lacking else 'register in ' + ' and '.join(lacking))


def _keys_from_json(no, prefix=''):
    """Every path in a translation dictionary, flattened to 'a.b.c'."""
    keys = set()
    for key, valor in no.items():
        rel_path = f'{prefix}.{key}' if prefix else key
        keys.add(rel_path)
        if isinstance(valor, dict):
            keys |= _keys_from_json(valor, rel_path)
    return keys


def translations(bases, report):
    """The i18n keys the module uses, against the ones the target has.

    This is the seam that cost the most and warns the least. A key that does not exist
    never throws, never fails a test and never shows in type-check: the screen renders
    `TIPS.TITLE.SOMETHING` in capitals, in production, and only a pair of eyes catches it.
    That is how `ROLESANDPERMISSIONS.PERMISSIONS.MANAGE` reached the parent in the Idea
    port, with the suite green.

    Reads the core `en.json` plus `locales/local/en.json`, which is how the app merges
    them. A key built at runtime (`t(`a.b.${x}`)`) becomes UNSURE: the prefix can be
    checked, never the whole branch.
    """
    ts = text_from(bases, {'.ts', '.vue'})

    available = set()
    for source_file in (CELER / 'src/locales/en.json', CELER / 'src/locales/local/en.json'):
        try:
            available |= _keys_from_json(json.loads(read_text_of(source_file) or '{}'))
        except json.JSONDecodeError:
            pass

    static_keys = set(re.findall(r"""(?<![\w$])\$?t\(\s*['"]([a-zA-Z][\w.]*)['"]""", ts))

    # A key passed as a PLAIN prop, with no `$t()`: `<MMainDialog title="tips.title">` —
    # the component is what calls `$t()` inside. These are invisible to a scan for `$t(`
    # and fail just as silently. Measured across the whole of celer: 18 cases, all on
    # `title`, all resolving, zero false positives — the shape only turns up where it
    # really is a key, so the search goes by shape rather than by a list of prop names.
    static_keys |= set(valor for _, valor in re.findall(
        r'(?<![:@\w-])([a-zA-Z]\w*)="([a-z][A-Za-z0-9]*(?:\.[A-Za-z0-9]\w*)+)"', ts))
    dynamic_keys = set(re.findall(r"(?<![\w$])\$?t\(\s*`([a-zA-Z][\w.]*)\.\$\{", ts))

    for key in sorted(static_keys):
        is_there = key in available
        report.add('translation key', key,
                      PRESENT if is_there else MISSING,
                      '' if is_there else 'the target does not have this key — the screen renders the raw key')

    for prefix in sorted(dynamic_keys):
        report.add('translation key', f'{prefix}.*',
                      PRESENT if prefix in available else UNSURE,
                      'key built at runtime: check the whole branch by hand')


# Namespaces Laravel itself ships in vendor — demanding a translation file for these
# would report an absence the framework already handles.
FRAMEWORK_LANG = ('auth', 'validation', 'passwords', 'pagination')


def php_translations(php, report):
    """The messages the API answers with, on the PHP side.

    The same silent seam as 14, on the other side of the house and forgotten for exactly
    that reason: I said out loud that this only scanned the front, and the Tip port carried
    three `__('tips.…')` across without the `lang/` files. Laravel does not throw on a
    missing translation — it returns the KEY ITSELF, so the API was answering
    `tips.anchorTaken` to the client, with the suite green because the tests assert on the
    field and never on the message.

    The check reads a key `<file>.<leaf>` as the leaf `<leaf>` inside `lang/en/<file>.php`
    (`__('tips.anchorTaken')` → `'anchorTaken'` in `lang/en/tips.php`). That Tip example is
    historical: `TipService` now throws the i18n key (`tips.errors.anchorTaken`) for the SPA to
    translate, and arcus no longer has a `tips.php`.
    """
    for key in sorted(set(re.findall(r"(?<![\w$])(?:__|trans)\(\s*'([a-z][\w.]*\.[\w.]+)'", php))):
        source_file, _, resto = key.partition('.')

        if source_file in FRAMEWORK_LANG:
            report.add('PHP translation', key, PRESENT, 'Laravel ships this one')
            continue

        target_file = ARCUS / f'lang/en/{source_file}.php'
        folha = resto.rsplit('.', 1)[-1]
        is_there = target_file.exists() and f"'{folha}'" in read_text_of(target_file)

        report.add('PHP translation', key,
                      PRESENT if is_there else MISSING,
                      '' if is_there else f'lang/en/{source_file}.php has no such key — without it the API '
                                       f'answers "{key}" to the client, with no error at all')


def migrations(bases, report):
    """Every model in the module needs its table created in the target.

    Not silent — the test dies in "no such table" — but it is work the port has to do and
    nothing listed it. Migrations live outside the module, so moving the directory leaves
    them behind.
    """
    for source_file in files_in(bases, {'.php'}):
        if 'Models' not in source_file.parts:
            continue

        text = read_text_of(source_file)
        match = re.search(r"protected \$table\s*=\s*'(\w+)'", text)
        table = match.group(1) if match else _likely_table(source_file.stem)

        existe = any(f'create_{table}_table' in c.name
                     for c in (ARCUS / 'database/migrations').glob('*.php'))

        report.add('model migration', f'{source_file.stem} → {table}',
                      PRESENT if existe else MISSING,
                      '' if existe else 'no create_..._table in the target')


def _likely_table(modelo: str) -> str:
    """Plural snake_case, the way Eloquent does it when nobody declares $table."""
    neve = re.sub(r'(?<!^)(?=[A-Z])', '_', modelo).lower()
    if neve.endswith('y'):
        return neve[:-1] + 'ies'
    return neve + ('es' if neve.endswith(('s', 'x', 'z', 'ch', 'sh')) else 's')


def shared_stores(bases, report):
    """A Pinia store the module uses that lives in `celer/src/stores`, outside it.

    The `@Stores` alias exists in the target, so the alias check passes and says nothing
    about the store itself.
    """
    ts = text_from(bases, {'.ts', '.vue'})
    barrel = read_text_of(CELER / 'src/stores/index.ts')

    # Only the ones coming from `@Stores`. A `useUserStore` imported from `@UserModule` is
    # a module store and the alias check already covers it — charging it for a file in
    # `celer/src/stores` reported an absence for something that never lived there.
    imported = set()
    for block in re.findall(r"import\s*\{([^}]*)\}\s*from\s*'@Stores'", ts):
        imported |= {unit_name.strip() for unit_name in block.split(',') if unit_name.strip()}

    # `use([A-Z]...)` and not `use(\w+)`: without the capital, the variable `userStore`
    # matches as `use` + `r` + `Store` and the report asks for an `r.store.ts`.
    for symbol in sorted(imported):
        match = re.fullmatch(r'use([A-Z]\w*)Store', symbol)
        if not match:
            continue

        unit_name = match.group(1)
        source_file = f'{unit_name[0].lower()}{unit_name[1:]}.store'
        is_there = source_file in barrel or (CELER / f'src/stores/{source_file}.ts').exists()

        report.add('shared store', symbol,
                      PRESENT if is_there else MISSING,
                      '' if is_there else f'celer/src/stores/{source_file}.ts does not exist')


def what_shared_components_pull(child, bases, report):
    """What the SHARED components pull, one level down.

    The hole the documentation itself admitted to: the pre-flight reported `ThePageHeader`
    as a shared component, but not what its NEW version had come to need. In the Idea port
    that was `useViewport` — a composable no file in the module imports and the port had to
    bring anyway, because the merged header depends on it.

    One level, not recursion: what matters is the hop the module does not declare. Deeper
    than that the target already resolves on its own, and the list would turn to noise.
    """
    ts = text_from(bases, {'.vue'})
    used = set(re.findall(r'<([A-Z][A-Za-z]*)[\s/>]', ts))
    own = {c.stem for c in files_in(bases, {'.vue'})}

    seen = set()
    for unit_name in sorted(used - own):
        source = next((c for c in child.rglob(f'src/components/**/{unit_name}.vue')), None)
        if not source:
            continue

        text = read_text_of(source)

        for block in re.findall(r"import\s*\{([^}]*)\}\s*from\s*'@Composables'", text):
            for symbol in (n.strip() for n in block.split(',') if n.strip()):
                if symbol in seen:
                    continue
                seen.add(symbol)

                is_there = symbol in read_text_of(CELER / 'src/composables/index.ts')
                report.add('what the shared component pulls', f'{unit_name} → {symbol}',
                              PRESENT if is_there else MISSING,
                              '' if is_there else f'{symbol} does not exist in celer/src/composables — '
                                               f'the module does not import it, {unit_name} does')

        for block in re.findall(r"import\s*\{([^}]*)\}\s*from\s*'@Stores'", text):
            for symbol in (n.strip() for n in block.split(',') if n.strip()):
                match = re.fullmatch(r'use([A-Z]\w*)Store', symbol)
                if not match or symbol in seen:
                    continue
                seen.add(symbol)

                short_form = match.group(1)
                source_file = f'{short_form[0].lower()}{short_form[1:]}.store'
                is_there = source_file in read_text_of(CELER / 'src/stores/index.ts')
                report.add('what the shared component pulls', f'{unit_name} → {symbol}',
                              PRESENT if is_there else MISSING,
                              '' if is_there else f'celer/src/stores/{source_file}.ts does not exist — '
                                               f'the module does not use it, {unit_name} does')


def shared_composables(bases, report):
    """A composable the module imports from `@Composables` that lives outside it.

    Same class as the shared store, and the `@Composables` alias exists in the target, so
    the alias check passes and says nothing about the composable itself. The Idea port
    brought `useViewport` along and nothing warned.
    """
    ts = text_from(bases, {'.ts', '.vue'})

    imported = set()
    for block in re.findall(r"import\s*\{([^}]*)\}\s*from\s*'@Composables'", ts):
        imported |= {unit_name.strip() for unit_name in block.split(',') if unit_name.strip()}

    barrel = read_text_of(CELER / 'src/composables/index.ts')
    for symbol in sorted(imported):
        source_file = symbol if symbol.startswith('use') else f'use{symbol}'
        is_there = source_file in barrel or (CELER / f'src/composables/{source_file}.ts').exists()
        report.add('shared composable', symbol,
                      PRESENT if is_there else MISSING,
                      '' if is_there else f'celer/src/composables/{source_file}.ts does not exist')


def shared_helpers(bases, report):
    """A helper the module imports from `@Helpers` that the target does not export.

    The sibling of the composable check, and it went missing for a reason worth writing down:
    `shared_composables` recognises a symbol by the `use` prefix, so a plain function was never
    a candidate. The layout/nav port imported `getLoginPath` from `@Helpers`, every other check
    passed, and the BUILD is what failed — "is not exported by src/helpers/index.ts", after the
    whole suite had gone green. That is the cheap end of this class; the expensive end is a
    helper that exists in the target under the same name and does something else.
    """
    ts = text_from(bases, {'.ts', '.vue'})

    imported = set()
    for block in re.findall(r"import\s*\{([^}]*)\}\s*from\s*'@Helpers'", ts):
        imported |= {name.strip() for name in block.split(',') if name.strip() and 'type ' not in name}

    barrels = read_text_of(CELER / 'src/helpers/index.ts')
    for nested in sorted((CELER / 'src/helpers').glob('*/index.ts')):
        barrels += '\n' + read_text_of(nested)

    for symbol in sorted(imported):
        is_there = re.search(rf'\b{re.escape(symbol)}\b', barrels) is not None
        report.add('shared helper', symbol,
                   PRESENT if is_there else MISSING,
                   '' if is_there else '@Helpers in the target does not export it')


def _utility_class_names(css: str) -> set:
    """The hand-written Tailwind utilities a stylesheet declares (`@utility name {`)."""
    return set(re.findall(r'@utility\s+([a-zA-Z0-9_-]+)', css))


def css_names(child, bases, report, app=''):
    """A CSS name the ported markup USES and the target's stylesheets must DEFINE.

    Two shapes, one failure. A custom property reached through `var(--x)` — including inside a
    Tailwind arbitrary value like `pb-[var(--mobile-nav-band)]` — and a hand-written utility
    class declared with `@utility`, which is how this project spells a motion duration so that
    the reduced-motion override can still reach it.

    Neither fails loudly. An undefined `var()` resolves to nothing and the property falls back
    to its initial value; an undeclared `@utility` class is simply not generated, so the element
    keeps whatever it had. The layout/nav port carried `motion-calm`, `motion-ease-settle` and
    `--mobile-nav-band` into a target that declared none of them: the collapse animation would
    have run at the browser default and the mobile bar would have sat on the content, with
    nothing red anywhere. Found by eye, which is the wrong instrument.
    """
    markup = text_from(bases, {'.vue', '.ts', '.css'})

    target_css = ''
    for sheet in sorted((CELER / 'src/styles').rglob('*.css')):
        target_css += '\n' + read_text_of(sheet)

    source_css = ''
    front = child / app if app else child
    for sheet in sorted((front / 'src/styles').rglob('*.css')):
        source_css += '\n' + read_text_of(sheet)

    for prop in sorted(set(re.findall(r'var\(\s*(--[a-zA-Z0-9-]+)', markup))):
        is_there = f'{prop}:' in target_css
        report.add('css custom property', prop,
                   PRESENT if is_there else MISSING,
                   '' if is_there else 'no target stylesheet defines it — `var()` resolves to nothing')

    # Only the utilities the SOURCE declares by hand are candidates: everything else in a class
    # attribute is Tailwind's own, and asking about those would drown the report.
    declared_here = _utility_class_names(target_css)
    for utility in sorted(_utility_class_names(source_css)):
        if not re.search(rf'["\'\s]{re.escape(utility)}[\s"\']', markup):
            continue
        is_there = utility in declared_here
        report.add('css utility', utility,
                   PRESENT if is_there else MISSING,
                   '' if is_there else 'the source declares it with @utility and the target does not')


def front_routes(bases, report):
    """If the module declares routes, the target's router has to mount them."""
    for base in bases:
        if not (base / 'routes.ts').exists():
            continue

        parts = base.parts
        if 'modules' not in parts:
            continue

        unit_name = parts[parts.index('modules') + 1]
        alias = f'@{unit_name}Module'
        router = read_text_of(CELER / 'src/libraries/router/routes.ts') + read_text_of(CELER / 'src/libraries/router/routes.local.ts')

        report.add('module routes in the router', f'{unit_name}Routes',
                      PRESENT if alias in router else MISSING,
                      '' if alias in router else 'the target router does not import this module')


def factories(php, report):
    for unit_name in sorted(set(re.findall(r'Database\\Factories\\(\w+Factory)', php))):
        is_there = (ARCUS / 'database/factories' / f'{unit_name}.php').exists()
        report.add('factory', unit_name, PRESENT if is_there else MISSING,
                      '' if is_there else 'not in arcus database/factories/')


def configs(php, report, project=''):
    for unit_name in sorted(set(re.findall(r"config\('(\w+)\.", php))):
        # `config/<project>.php` is the config named after the product; the transform
        # rewrites it, so in the target it is called `pendulum.php`.
        if unit_name == project:
            is_there = (ARCUS / 'config/pendulum.php').exists()
            report.add('own config file', f'config/{unit_name}.php → config/pendulum.php',
                          PRESENT if is_there else MISSING,
                          '' if is_there else 'the config named after the project')
            continue

        if unit_name in {'app', 'auth', 'cache', 'database', 'filesystems', 'mail', 'queue',
                    'sanctum', 'services', 'session', 'cors', 'logging'}:
            continue
        is_there = (ARCUS / 'config' / f'{unit_name}.php').exists()
        report.add('own config file', f'config/{unit_name}.php', PRESENT if is_there else MISSING,
                      '' if is_there else 'and its environment variable')


def rate_limiters(php, report):
    provider = read_text_of(ARCUS / 'app/Providers/AppServiceProvider.php')
    for unit_name in sorted(set(re.findall(r"throttle:([a-z][\w-]*)", php))):
        is_there = f"RateLimiter::for('{unit_name}'" in provider
        report.add('named rate limiter', f'throttle:{unit_name}', PRESENT if is_there else MISSING,
                      '' if is_there else 'a route references a limiter the target does not register')


def permissions(php, report):
    helper = read_text_of(ARCUS / 'app/Modules/Auth/Helpers/PermissionGroupsHelper.php')
    keys = sorted(set(re.findall(r"permission:([a-z][\w.]*)", php)))

    for key in keys:
        is_there = f"'{key}'" in helper
        report.add('permission key', key, PRESENT if is_there else MISSING,
                      '' if is_there else 'not in PermissionGroupsHelper')

    # Declaring the key is half of it. The other half is WHO receives it, and that is a
    # decision rather than a mechanic: `ManagerSeeder` is where a role is denied what it
    # must not have. It came up as a blind spot in BOTH audited ports, always for the same
    # reason — a management key the ordinary role cannot be handed for free.
    management_keys = [c for c in keys if c.rsplit('.', 1)[-1] in ('manage', 'delete', 'moderate')]
    if management_keys:
        seeder = read_text_of(ARCUS / 'database/seeders/ManagerSeeder.php')
        lacking = [c for c in management_keys if f"'{c}'" not in seeder]
        report.add('role receiving the permission', ', '.join(management_keys),
                      PRESENT if not lacking else UNSURE,
                      'ManagerSeeder.php already decides about these' if not lacking
                      else 'ManagerSeeder.php does not mention ' + ', '.join(lacking)
                           + ' — decide which roles receive them before running the seeder')


def mailables(child, php, report):
    """The message the module sends lives OUTSIDE it, and brings a template along.

    `app/Mail/XxxMail.php` is not in the module's directory, so moving it does not take the
    mailable — and the template it declares is a level deeper still. None of this breaks a
    test: `Mail::fake()` never even builds the message, so the suite stays green and the
    failure turns up when somebody actually sends the e-mail.

    Returns the PHP of the mailables it found so the Blade view check scans their templates
    as if they belonged to the module.
    """
    unit_names = sorted(set(re.findall(r'new\s+(\w+Mail)\s*\(', php)))
    extra_text = []

    for unit_name in unit_names:
        source = next((c for c in child.rglob(f'app/Mail/{unit_name}.php')), None)
        in_target = (ARCUS / f'app/Mail/{unit_name}.php').exists()

        report.add('e-mail message', f'{unit_name}.php',
                      PRESENT if in_target else MISSING,
                      '' if in_target else 'not in arcus app/Mail/ — and a missing Mailable does '
                                            'not break a test using Mail::fake()')

        if source:
            extra_text.append(read_text_of(source))

    return '\n'.join(extra_text)


def blade_views(php, report):
    """The Blade templates the module renders.

    Two spellings, not one. `view('x.y')` is the helper call; `view: 'x.y'` is how a
    Mailable declares its own, inside `new Content(...)`. Looking only for the first let the
    entire verification e-mail template through — and a Mailable with no template does not
    fail a test using `Mail::fake()`, only when somebody sends the message for real.
    """
    # The hyphen counts: `emails.verification-code` is a perfectly ordinary template name,
    # and without it in the set the name comes out truncated to `emails.verification`, which
    # never exists — an invented absence pointing at a file nobody ever wrote.
    found_any = (set(re.findall(r"view\('([\w.-]+)'", php))
                   | set(re.findall(r"view:\s*'([\w.-]+)'", php)))

    for unit_name in sorted(found_any):
        rel_path = ARCUS / 'resources/views' / (unit_name.replace('.', '/') + '.blade.php')
        report.add('Blade view', unit_name, PRESENT if rel_path.exists() else MISSING,
                      '' if rel_path.exists() else f'resources/views/{unit_name.replace(".", "/")}.blade.php')


def _without_comments_or_strings(php: str) -> str:
    """Comments and string literals out: neither one is a function call."""
    php = re.sub(r'/\*[\s\S]*?\*/|//[^\n]*|(?<!:)#[^\n]*', '', php)
    return re.sub(r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"", "''", php)


def _names_that_are_not_personas():
    """Everything that looks like a function call in a test and is not a project persona.

    The list used to be written by hand and so lived permanently out of date — `pathinfo`,
    `tempnam` and `foreach` passed for missing personas. PHP can say which names are its
    own; what it cannot say are the language constructs (which are not functions) and Pest's
    vocabulary. Those two stay written down, because they are short and do not grow.
    """
    internals = set()
    try:
        output = subprocess.run(
            ['php', '-r', 'echo implode(",", get_defined_functions()["internal"]);'],
            capture_output=True, text=True, timeout=20,
        )
        internals = {unit_name for unit_name in output.stdout.split(',') if unit_name}
    except (OSError, subprocess.SubprocessError):
        pass

    # A Laravel global helper (`collect`, `config`, `route`...) is neither a PHP internal
    # nor a project persona: the framework declares it in vendor.
    for source_file in (PENDULUM / 'arcus/vendor/laravel/framework/src/Illuminate').rglob('helpers.php'):
        internals |= set(re.findall(r'^\s*function\s+(\w+)\s*\(', read_text_of(source_file), re.M))

    constructs = {'foreach', 'for', 'while', 'switch', 'match', 'list', 'isset', 'unset',
                   'echo', 'print', 'array', 'function', 'fn', 'catch', 'elseif', 'return',
                   'include', 'require', 'empty', 'exit', 'die', 'clone', 'new', 'throw'}
    pest_dsl = {'test', 'it', 'expect', 'describe', 'beforeEach', 'afterEach', 'beforeAll',
                'afterAll', 'dataset', 'uses', 'todo', 'skip', 'covers', 'mock'}

    return internals | constructs | pest_dsl


def test_helpers(bases, report):
    test_files = [c for c in files_in(bases, {'.php'}) if 'test' in str(c).lower()]
    if not test_files:
        return

    # Cleaned FILE BY FILE and only then joined: an unbalanced quote in a heredoc throws
    # the string pairing of the next file out of step, and `alert(` from a
    # `'<script>alert(1)</script>'` came back as a missing persona.
    body = '\n'.join(_without_comments_or_strings(read_text_of(c)) for c in test_files)
    # Comments and string literals OUT before looking for calls. `toContain(` quoted in a
    # sentence explaining the assertion is not a call, and `alert(` inside a sanitiser
    # fixture's `'<script>alert(1)</script>'` even less so. Both were being charged as
    # missing personas. Same trap as the slot check, which had already learnt this.
    defined_here = set(re.findall(r'^function\s+(\w+)', body, re.M))
    # A global function never follows `->` or `::`. Without that distinction the report
    # fills with `assertOk()` and `assertNotFound()`, which are methods, and drowns the
    # names that matter — the personas the project declared in its own Pest.php.
    # With arguments too: a persona called `makeSomething($this, $actor)` is as global as
    # one taking none, and the first port only had the second kind.
    called = set(re.findall(r'(?<![>:$\w])\b([a-z][A-Za-z]{3,})\s*\(', body))

    pest = read_text_of(ARCUS / 'tests/Pest.php')
    ignore = _names_that_are_not_personas()

    for unit_name in sorted(called - defined_here - ignore):
        # No third path. There used to be an `elif` here that skipped a name when it
        # appeared in `body.split('function')[0]` — "everything before the word function
        # first occurs in the whole text", an arbitrary prefix with no meaning at all.
        # It swallowed findings in silence, and on its own held up a test I believed
        # covered the comment stripping. Mutation testing is what showed it.
        if f'function {unit_name}(' in pest:
            report.add('global test helper', f'{unit_name}()', PRESENT)
        else:
            report.add('global test helper', f'{unit_name}()', UNSURE,
                          'not in arcus tests/Pest.php — check whether it is global or a method')


def components_and_slots(bases, report):
    vues = [c for c in files_in(bases, {'.vue'})]
    if not vues:
        return

    body = '\n'.join(read_text_of(c) for c in vues)
    own = {c.stem for c in files_in(bases, {'.vue'})}

    # components used in the template that are NOT the module's
    used = set(re.findall(r'<(The[A-Z][A-Za-z]+|[AMOT][A-Z][A-Za-z]+)', body))
    for unit_name in sorted(used - own):
        is_there = any((CELER / 'src' / p).exists() for p in
                    (f'components/atoms/{unit_name}', f'components/molecules/{unit_name}',
                     f'components/organisms/{unit_name}', f'components/unique/{unit_name}',
                     f'layouts/{unit_name}'))
        report.add('shared component', unit_name, PRESENT if is_there else MISSING,
                      '' if is_there else 'used in the template and does not exist in celer')

    # Slots asked of shared components.
    #
    # A slot's owner is the NEAREST component OPENED before it, not the first one to turn
    # up in a window: `<TheLayout>` wraps `<ThePageHeader>`, and a crude proximity search
    # hands the second one's slots to the first.
    shared = used - own
    pairs = set()

    for source_file in vues:
        # Comments out, BEFORE looking for slots. This project explains its decisions in
        # prose inside the template, and a `#toolbar` quoted in a comment above the tag
        # that uses it reads as a slot asked of the previous component — which is how
        # PrimeVue `<Editor>`'s `#toolbar` became an accusation against MImageUpload.
        content = re.sub(r'<!--[\s\S]*?-->|/\*[\s\S]*?\*/', '', read_text_of(source_file))
        openings = [(m.start(), m.group(1)) for m in
                     re.finditer(r'<([A-Z][A-Za-z]+)', content)]

        for slot in re.finditer(r'#([a-z][A-Za-z]+)', content):
            unit_name = slot.group(1)
            if unit_name == 'default':
                continue

            # The owner is the nearest opening, whoever it belongs to. If it belongs to a
            # third-party component — PrimeVue's `<Editor>`, say — the question is not ours:
            # filtering BEFORE choosing would hand the slot to the wrong component, which is
            # how the Editor's `#toolbar` became `MImageUpload #toolbar`.
            openings_before = [n for pos, n in openings if pos < slot.start()]
            if openings_before and openings_before[-1] in shared:
                pairs.add((openings_before[-1], unit_name))

    for dono, slot in sorted(pairs):
        alvos = list((CELER / 'src').rglob(f'{dono}.vue'))
        declara = any(f'name="{slot}"' in read_text_of(a) for a in alvos)
        report.add('shared component slot', f'{dono} #{slot}',
                      PRESENT if declara else MISSING,
                      '' if declara else 'the target component does NOT declare this slot — it fails in silence')


def organisms_mounted_elsewhere(bases, report):
    """An organism the module DEFINES and never uses is mounted somewhere else.

    The "New idea" button opened and nothing happened: `OIdeaCaptureDialog` lives once in
    `TheLayout`, above every authenticated screen, and copying the module did not take it.
    No error, no dialog. The right question is not "which organisms does the module use" —
    it is "which does it define and not use", because those depend on a mount point the
    target may not have.
    """
    vues = list(files_in(bases, {'.vue'}))
    definidos = {c.stem for c in vues if c.stem.startswith('O')}
    if not definidos:
        return

    layout = read_text_of(CELER / 'src/layouts/TheLayout/TheLayout.vue')

    for unit_name in sorted(definidos):
        usado_dentro = any(re.search(rf'<{unit_name}\b', read_text_of(c)) for c in vues if c.stem != unit_name)
        if usado_dentro:
            continue

        mounted = f'<{unit_name}' in layout
        report.add('organism mounted outside the module', unit_name,
                      PRESENT if mounted else MISSING,
                      '' if mounted else 'defined and never used in the module: the source mounts it '
                                         'in a layout, and the target does not yet')


def nav_entry(bases, report):
    """A module with screens and no nav entry is unreachable, and throws nothing.

    Asked at MODULE level, not per route: not every route belongs in the nav — detail,
    redirect and compatibility routes do not — but a module whose routes appear nowhere in
    the nav is only reachable by typing the URL.

    Found by the audit of the Tip port: one line in `TheNavbar.vue` that nothing mentioned.
    """
    for base in bases:
        source_file = base / 'routes.ts'
        has_views = (base / 'views').is_dir()

        # Not every module declares its routes inside itself — lifeshifter's Idea has no
        # `routes.ts` and still has screens. Without this second trigger the question was
        # never asked of the very module that prompted it.
        if not source_file.exists():
            if has_views:
                report.add('nav entry', f'{base.name} (telas, rotas declaradas fora)',
                              UNSURE, 'cannot read route names from here — check '
                                      'TheNavbar.vue by hand')
            continue

        # A route name carries the locale (`tips-en`, `tips-pt-BR`) and the nav stores the
        # STEM (`tips`) — `getI18nRouteName` appends the suffix at runtime. Comparing the
        # whole name never matches, and the module comes out as if it had no nav entry.
        unit_names = {re.sub(r'-[a-z]{2}(-[A-Z]{2})?$', '', unit_name)
                 for unit_name in re.findall(r"name:\s*'([\w-]+)'", read_text_of(source_file))}
        if not unit_names:
            continue

        menu = read_text_of(CELER / 'src/components/unique/TheNavbar/TheNavbar.vue') + \
            read_text_of(CELER / 'src/components/unique/TheNavbar/navItems.local.ts')
        in_nav = sorted(n for n in unit_names if f"routeName: '{n}'" in menu)

        report.add('nav entry', f'{base.name} ({len(unit_names)} rota(s))',
                      PRESENT if in_nav else UNSURE,
                      f"TheNavbar.vue, via {', '.join(in_nav)}" if in_nav
                      else 'no route of this module appears in TheNavbar.vue or in '
                           'navItems.local.ts — check whether that is deliberate')


def module_seeders(child, bases, report):
    """The module's seeder lives OUTSIDE it, and has to be CALLED to exist.

    Two different absences in one file. `<X>Seeder.php` lives in `database/seeders/`, so
    moving the module's directory does not take it; and a seeder that arrives without a line
    in `DatabaseSeeder` never runs, which breaks no test and only turns up when somebody
    wonders why the database is empty.

    The line goes in `DatabaseSeeder`, never in `seeders.local.php`: that file is LOCAL and
    reaches no child, so a bootstrap module registered there seeds the parent and nowhere
    else.

    Found by the audit of the Tip port — three seeder files the pre-flight never mentioned.
    """
    # One name per module: the API and the front both give `Tip`, and reporting the same
    # absence twice only makes the report look bigger than it is.
    unit_names = []
    for base in bases:
        parts = base.parts
        key = 'Modules' if 'Modules' in parts else ('modules' if 'modules' in parts else None)
        if key and parts[parts.index(key) + 1] not in unit_names:
            unit_names.append(parts[parts.index(key) + 1])

    for unit_name in unit_names:
        seeder = f'{unit_name}Seeder'

        source = next((c for c in child.rglob(f'database/seeders/{seeder}.php')), None)
        if not source:
            continue

        in_target = (ARCUS / f'database/seeders/{seeder}.php').exists()
        report.add('module seeder', f'{seeder}.php',
                      PRESENT if in_target else MISSING,
                      '' if in_target else 'exists at the source and not in the target')

        chamado = f'{seeder}::class' in read_text_of(ARCUS / 'database/seeders/DatabaseSeeder.php')
        report.add('module seeder', f'{seeder} called in DatabaseSeeder',
                      PRESENT if chamado else MISSING,
                      '' if chamado else 'without the call it never runs — and in DatabaseSeeder, '
                                         'never in seeders.local.php, which is LOCAL')


def project_routes(child, bases, report):
    """The module's routes are NOT in the module.

    The project declares them in `routes/api.local.php`, outside the directory being moved
    — so `throttle:` and `permission:` written there are invisible to a scan of the module.
    That is how the two named rate limiters only turned up when Laravel threw at runtime.

    Here the search goes after them: it finds the module's controllers, looks for the lines
    naming them in the project's route files, and pulls in the middleware from those lines
    and from the groups wrapping them. It over-reports rather than under-reports — an item
    checked for nothing costs seconds; one missed cost an afternoon.
    """
    controllers = {c.stem for base in bases for c in base.rglob('*Controller.php')}
    if not controllers:
        return '', ''

    route_php, found_any = [], False
    for source_file in sorted(child.glob('api.*/routes/*.php')):
        content = read_text_of(source_file)
        if not any(unit_name in content for unit_name in controllers):
            continue

        found_any = True
        rows = content.splitlines()

        # `->middleware($tipsRead)` with `$tipsRead = ['auth', 'permission:tips.read']`
        # a few lines above: the key is not on the route's line. It follows the variable —
        # but only those THIS module's routes use, or the project's whole route file comes
        # in and the report fills with other modules' keys.
        do_modulo = [l for l in rows if any(unit_name in l for unit_name in controllers)]
        variables = set(re.findall(r'->middleware\(\s*(\$\w+)', '\n'.join(do_modulo)))
        route_php += [l for l in rows
                      if any(re.match(rf'\s*\{v}\s*=', l) for v in variables)]

        for index, line in enumerate(rows):
            if any(unit_name in line for unit_name in controllers):
                route_php.append(line)
                # the group wrapping the route carries its middleware
                for anterior in range(index, max(index - 60, -1), -1):
                    if 'Route::middleware(' in rows[anterior]:
                        route_php.append(rows[anterior])
                        break

    if not found_any:
        report.add('module routes', 'no route file mentions the controllers',
                      UNSURE, 'check where the project declares this module routes')

    # The TARGET side. This used to say something only when the source had no routes —
    # which stayed quiet in exactly the normal case, and `routes/api.php` came out as a
    # blind spot in both audited ports. A bootstrap module's routes go in the core
    # `api.php`, never in `api.local.php`, which is LOCAL and reaches no child.
    target_routes = ''.join(read_text_of(c) for c in sorted((ARCUS / 'routes').glob('*.php')))
    for controller in sorted(controllers):
        is_there = controller in target_routes
        report.add('module routes in the target', controller,
                      PRESENT if is_there else MISSING,
                      '' if is_there else 'no route in arcus/routes/api.php calls this controller')

    return '\n'.join(route_php), ', '.join(sorted(controllers))


def conf_value(child, key):
    """A value from the child's `.cortex-sync.conf` — the same source the sync reads.

    Never guessed from the folder: `impressao` lives at `impressao/new.impressao` and its APP is
    `app.impressao`, so neither name follows the directory. Guessing compared the module against
    the wrong normaliser and reported every file as different.
    """
    source_file = child / '.cortex-sync.conf'
    if not source_file.exists():
        return ''

    for line in source_file.read_text(encoding='utf-8').splitlines():
        if line.strip().startswith(f'{key}='):
            return line.split('=', 1)[1].strip()
    return ''


def project_name(child):
    return conf_value(child, 'PROJECT')


def main() -> int:
    if len(sys.argv) < 3:
        print(__doc__)
        return 2

    rel_path, relatives = sys.argv[1], sys.argv[2:]
    child = ROOT / rel_path
    bases = [child / rel for rel in relatives]

    # The project name is NOT the folder name: impressao lives at `impressao/new.impressao`.
    # The child declares its own name, in the same file the sync reads.
    project = project_name(child) or Path(rel_path).name

    lacking = [b for b in bases if not b.exists()]
    if lacking:
        for b in lacking:
            print(f'!! path does not exist: {b}')
        return 2

    php = text_from(bases, {'.php'})
    ts = text_from(bases, {'.ts', '.vue'})

    report = Report()

    # The routes live outside the module; their middleware counts as its reference.
    php += '\n' + project_routes(child, bases, report)[0]
    module_seeders(child, bases, report)
    composer_dependencies(php, report)
    npm_dependencies(ts, report)
    aliases(ts, report)
    own_module_alias(bases, report)
    factories(php, report)
    configs(php, report, project)
    rate_limiters(php, report)
    permissions(php, report)
    # The mailables' text joins the Blade view scan: the template they declare is as much
    # part of the port as one a controller renders.
    blade_views(php + '\n' + mailables(child, php, report), report)
    test_helpers(bases, report)
    components_and_slots(bases, report)
    organisms_mounted_elsewhere(bases, report)
    translations(bases, report)
    php_translations(php, report)
    migrations(bases, report)
    shared_stores(bases, report)
    front_routes(bases, report)
    shared_composables(bases, report)
    shared_helpers(bases, report)
    css_names(child, bases, report, conf_value(child, 'APP'))
    what_shared_components_pull(child, bases, report)
    nav_entry(bases, report)
    report.render()

    return 1 if report.missing_total else 0


if __name__ == '__main__':
    sys.exit(main())
