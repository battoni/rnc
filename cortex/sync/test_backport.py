#!/usr/bin/env python3
"""Tests for the back-port tools — the pre-flight, the deep diff and the audit.

Run:  python3 cortex/sync/test_backport.py   (exit 0 = pass, 1 = fail)

WHY THIS EXISTS. These tools decide whether a port can start and whether it finished
whole, and their only validation used to be running them by hand against two real ports.
That is how three of my own defects surfaced — the variable `userStore` charged as
`use` + `r` + `Store`, a module store charged against a directory it never lived in, and
comments and string literals read as function calls. All three are tested here by name,
because a defect that already happened is the cheapest one to predict.

Most of it is pure. What needs a target on disk builds a minimal tree in tempfile and
points the module globals at it — the tools read `CELER` and `ARCUS` as globals, so
swapping them is enough and nothing had to be refactored to be testable.
"""
from __future__ import annotations

import json
import sys
import tempfile
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import backport_diff as bd  # noqa: E402
import backport_preflight as pf  # noqa: E402

failures: list[str] = []


def check(name: str, cond: bool) -> None:
    if not cond:
        failures.append(name)
    print(f"  {'✓' if cond else '✗'} {name}")


def write_file(root: Path, rel_path: str, content: str = '') -> Path:
    target_file = root / rel_path
    target_file.parent.mkdir(parents=True, exist_ok=True)
    target_file.write_text(content, encoding='utf-8')
    return target_file


def run_seam(seam, bases):
    """Runs one seam and returns {item: (state, note)}."""
    report = pf.Report()
    seam(bases, report)
    return {item: (state, note) for _, item, state, note in report.rows}


def rows(seam, bases):
    """The RAW report rows, in order and with repetition.

    `run_seam` returns a dict, and a dict eats duplicates: something reported twice
    becomes one entry and no test can see it. To count repetition, read from here.
    """
    report = pf.Report()
    seam(bases, report)
    return [item for _, item, _, _ in report.rows]


def state(found, item):
    """Estado de um item, ou '—' se ele nem foi relatado.

    Never index `found` directly: when a seam stops seeing something, indexing raises
    KeyError and the suite dies halfway, taking every other result with it. An item that
    disappeared has to become ONE readable failure, not a traceback.
    """
    return found.get(item, ('—', ''))[0]


# ═══════════════════════════════════════════════════════════════════════════
print('\n─── pure: the table name Eloquent assumes ───')

check("a plain model becomes a plural", pf._likely_table('Tip') == 'tips')
check("CamelCase becomes plural snake_case", pf._likely_table('TipHistory') == 'tip_histories')
check("ending in s takes es", pf._likely_table('Address') == 'addresses')
check("ending in y becomes ies", pf._likely_table('Company') == 'companies')

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── pure: a comment and a string literal are not calls ───')

PHP_ARMADILHA = """<?php
// asserting `toContain('ideas/')` could never have caught a regression.
/* another comment with oneMoreCall() inside */
$response = postJson('/api/x', ['body' => '<script>alert(1)</script>']);
minhaPersona($this);
"""
limpo = pf._without_comments_or_strings(PHP_ARMADILHA)
check("toContain quoted in a comment disappears", 'toContain' not in limpo)
check("a call inside a /* */ block disappears", 'maisUmaChamada' not in limpo)
check("alert inside a string disappears", 'alert' not in limpo)
check("the real call survives", 'minhaPersona' in limpo)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── pure: the keys of a translation dictionary ───')

ARVORE = {'tips': {'title': 'x', 'form': {'anchor': 'y'}}, 'common': {'save': 'z'}}
keys = pf._keys_from_json(ARVORE)
check("a nested leaf key", 'tips.form.anchor' in keys)
check("the branch counts too", 'tips.form' in keys)
check("a shallow key", 'common.save' in keys)
check("a key that does not exist never appears", 'tips.missing' not in keys)

# ═══════════════════════════════════════════════════════════════════════════
print("\n─── pure: the diff's normaliser comes from .cortex-sync.conf ───")

normalise = bd.normaliser({'PROJECT': 'impressao', 'API': 'api.impressao', 'APP': 'app.impressao'})
check("api.<project> becomes API", normalise('api.impressao/app/X') == 'API/app/X')
check("arcus becomes API too", normalise('arcus/app/X') == 'API/app/X')
check("app.<project> becomes APP", normalise('app.impressao/src') == 'APP/src')
check("the child's origin and the parent's collapse together",
      normalise('origin: impressao') == normalise('origin: pendulum') == 'origin: ORIGIN')

with tempfile.TemporaryDirectory() as tmp:
    child = Path(tmp)
    write_file(child, '.cortex-sync.conf', 'PROJECT=impressao\nAPP=app.impressao\nAPI=api.impressao\n')
    conf = bd.read_conf(child)
    check("the child's conf is read", conf['PROJECT'] == 'impressao' and conf['API'] == 'api.impressao')
    check("a missing conf returns empty rather than raising", bd.read_conf(Path(tmp) / 'nao-existe') == {})
    check("the project name comes from the conf, not the folder", pf.project_name(child) == 'impressao')

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── alias: all THREE files, and vitest writes it without the @ ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.CELER = Path(tmp) / 'celer'
    write_file(pf.CELER, 'vite.config.ts', "'@TipModule': getPath('./src/modules/Tip'),\n'@Meio': x,")
    write_file(pf.CELER, 'tsconfig.app.json', '"@TipModule": ["./src/modules/Tip"],\n"@Meio": [],')
    write_file(pf.CELER, 'vitest.config.ts', "{ alias: 'TipModule', path: './src/modules/Tip' },")

    check("an alias present in all three is not charged", pf.where_alias_is_missing('@TipModule') == [])
    check("vitest is recognised even written without the @",
          'vitest.config.ts' not in pf.where_alias_is_missing('@TipModule'))
    check("missing only from vitest, vitest is what is named",
          pf.where_alias_is_missing('@Meio') == ['vitest.config.ts'])
    check("absent from all three names all three", len(pf.where_alias_is_missing('@Fantasma')) == 3)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── i18n: the seam that fails in silence ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.CELER = Path(tmp) / 'celer'
    write_file(pf.CELER, 'src/locales/en.json', '{"tips": {"title": "T"}, "common": {"save": "S"}}')
    write_file(pf.CELER, 'src/locales/local/en.json', '{"projeto": {"proprio": "P"}}')

    module = Path(tmp) / 'modulo'
    write_file(module, 'View.vue', """
      <template>
        <MMainDialog title="tips.title" />
        <ThePageHeader title="tips.ausente" />
        <span>{{ $t('common.save') }}</span>
        <span>{{ $t('tips.naoExiste') }}</span>
        <span>{{ $t('projeto.proprio') }}</span>
        <Button icon="pi pi-user" severity="danger" />
      </template>
      <script setup lang="ts">
      const rotulo = t(`tips.eventos.${nome}`);
      </script>
    """)
    found = run_seam(pf.translations, [module])

    check("an existing key through $t() passes", state(found, 'common.save') == pf.PRESENT)
    check("a key from locales/local counts too", state(found, 'projeto.proprio') == pf.PRESENT)
    check("a missing key through $t() is an absence", state(found, 'tips.naoExiste') == pf.MISSING)
    check("a key passed as a plain prop is seen", 'tips.title' in found)
    check("a missing plain-prop key is an absence", state(found, 'tips.ausente') == pf.MISSING)
    check("a prop that is not key-shaped is no false positive",
          not any(valor.startswith('pi ') or valor == 'danger' for valor in found))
    check("a key built at runtime is a doubt, not an absence",
          state(found, 'tips.eventos.*') == pf.UNSURE)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── e-mail message: it lives outside the module and brings a template ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.ARCUS = Path(tmp) / 'arcus'
    write_file(pf.ARCUS, 'app/Mail/EmailVerificationCodeMail.php')
    write_file(pf.ARCUS, 'resources/views/emails/verification-code.blade.php')

    child = Path(tmp) / 'filho'
    write_file(child, 'api.x/app/Mail/EmailVerificationCodeMail.php',
             "return new Content(view: 'emails.verification-code');")
    write_file(child, 'api.x/app/Mail/SumidaMail.php', "return new Content(view: 'emails.sumido');")

    PHP = "Mail::to($x)->send(new EmailVerificationCodeMail($code)); new SumidaMail();"
    report = pf.Report()
    extra = pf.mailables(child, PHP, report)
    found = {item: (est, note) for _, item, est, note in report.rows}

    check("a mailable present in the target passes",
          state(found, 'EmailVerificationCodeMail.php') == pf.PRESENT)
    check("a mailable missing from the target is reported", state(found, 'SumidaMail.php') == pf.MISSING)
    check("the mailable's text comes back, so its template gets scanned",
          'emails.verification-code' in extra and 'emails.sumido' in extra)

    report = pf.Report()
    pf.blade_views(PHP + '\n' + extra, report)
    seen = {item: est for _, item, est, _ in report.rows}
    check("the template only the mailable declares is checked",
          seen.get('emails.verification-code') == pf.PRESENT
          and seen.get('emails.sumido') == pf.MISSING)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── Blade view: both spellings ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.ARCUS = Path(tmp) / 'arcus'
    write_file(pf.ARCUS, 'resources/views/share/news.blade.php')
    write_file(pf.ARCUS, 'resources/views/share/news-preview.blade.php')
    write_file(pf.ARCUS, 'resources/views/emails/verification-code.blade.php')

    # The hyphen has to hold for BOTH spellings, not only the Mailable's.
    PHP = ("return view('share.news-preview');\n"
           "return view('share.news');\n"
           "return new Content(view: 'emails.verification-code');\n"
           "return view('share.sumida');\n"
           "return new Content(view: 'emails.sumido');\n")
    report = pf.Report()
    pf.blade_views(PHP, report)
    found = {item: (est, note) for _, item, est, note in report.rows}

    check("view() with the template present passes", state(found, 'share.news') == pf.PRESENT)
    check("view() with a hyphen in the name is not truncated",
          state(found, 'share.news-preview') == pf.PRESENT)
    check("a Mailable's view: is seen too",
          state(found, 'emails.verification-code') == pf.PRESENT)
    check("view() with no template is an absence", state(found, 'share.sumida') == pf.MISSING)
    check("view: with no template is an absence", state(found, 'emails.sumido') == pf.MISSING)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── npm: what the module imports that is not relative ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.CELER = Path(tmp) / 'celer'
    write_file(pf.CELER, 'package.json', '{"dependencies": {"quill": "^2.0", "@vueuse/core": "^11"}}')

    TS = ("import Quill from 'quill';\n"
          "import { useX } from '@vueuse/core';\n"
          "import { useY } from 'ghost-package';\n"
          "import { A } from './local';\n"
          "import { B } from '@Stores';\n"
          "import { C } from '@/test/mount';\n")
    report = pf.Report()
    pf.npm_dependencies(TS, report)
    found = {item: state for _, item, state, _ in report.rows}

    check('a declared package passes', state(found, 'quill') == pf.PRESENT)
    check('a scoped package keeps both segments', state(found, '@vueuse/core') == pf.PRESENT)
    check('a package not in package.json is an absence', state(found, 'ghost-package') == pf.MISSING)
    check('a relative import is not a package', './local' not in found)
    check('a module alias is not a package', not any(i.startswith('@Stores') for i in found))
    check('the @/ alias is not a package', not any(i.startswith('@/') for i in found))
    # This is the check whose only variable collision slipped through for want of a test:
    # the loop variable was renamed onto the one holding the manifest text, so every
    # package was compared against itself and six real dependencies came out missing.
    check('the manifest is not compared against itself',
          len([i for i in found if state(found, i) == pf.PRESENT]) == 2)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── composer: a namespace is not a package name ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.ARCUS = Path(tmp) / 'arcus'
    write_file(pf.ARCUS, 'composer.json', '{"require": {"spatie/laravel-data": "^4.0"}}')
    write_file(pf.ARCUS, 'vendor/composer/installed.json', json.dumps({'packages': [
        {'name': 'nesbot/carbon', 'autoload': {'psr-4': {'Carbon\\': 'src/'}}},
        # The SHORTER prefix comes first ON PURPOSE, and belongs to a package the
        # composer.json does not declare. Without matching the longest first,
        # `Spatie\LaravelData\Data` falls into this one and comes out as a doubt rather
        # than present — and with a favourable insertion order the test would pass
        # without testing anything.
        {'name': 'spatie/outro-qualquer', 'autoload': {'psr-4': {'Spatie\\': 'src/'}}},
        {'name': 'spatie/laravel-data', 'autoload': {'psr-4': {'Spatie\\LaravelData\\': 'src/'}}},
    ]}))

    PHP = ('use Carbon\\Carbon;\n'
           'use Spatie\\LaravelData\\Data;\n'
           'use Fantasma\\Coisa;\n'
           'use RuntimeException;\n'
           'use App\\Models\\User;\n'
           'use Illuminate\\Support\\Str;\n')
    report = pf.Report()
    pf.composer_dependencies(PHP, report)
    found = {item: (est, note) for _, item, est, note in report.rows}

    check("a package declared in composer.json passes",
          state(found, 'Spatie → spatie/laravel-data') == pf.PRESENT)
    check("a two-segment prefix resolves", 'Spatie → spatie/laravel-data' in found)
    check("a merely transitive package is a doubt, not an absence",
          state(found, 'Carbon → nesbot/carbon') == pf.UNSURE)
    check("a namespace no package provides is an absence",
          state(found, 'Fantasma') == pf.MISSING)
    check("PHP's global namespace is not charged", 'RuntimeException' not in found)
    check("the house namespace is not charged", not any(i.startswith('App') for i in found))
    check("the framework's namespace is not charged", not any(i.startswith('Illuminate') for i in found))

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── PHP i18n: the API answers with the raw key ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.ARCUS = Path(tmp) / 'arcus'
    write_file(pf.ARCUS, 'lang/en/tips.php', "<?php return ['anchorTaken' => 'x'];")

    PHP = ("__('tips.anchorTaken') __('tips.semArquivo') __('outro.coisa') "
           "__('auth.failed') __('Uma frase inteira sem ponto')")
    report = pf.Report()
    pf.php_translations(PHP, report)
    found = {item: (est, note) for _, item, est, note in report.rows}

    check("a key present in lang/ passes", state(found, 'tips.anchorTaken') == pf.PRESENT)
    check("a key missing from the right file is reported", state(found, 'tips.semArquivo') == pf.MISSING)
    check("a lang file that does not exist is reported", state(found, 'outro.coisa') == pf.MISSING)
    check("the framework's namespace is not charged", state(found, 'auth.failed') == pf.PRESENT)
    check("a literal with no dot is not treated as a file key",
          not any(' ' in item for item in found))

# ═══════════════════════════════════════════════════════════════════════════
print("\n─── store: yesterday's two defects, by name ───")

with tempfile.TemporaryDirectory() as tmp:
    pf.CELER = Path(tmp) / 'celer'
    write_file(pf.CELER, 'src/stores/index.ts', "export * from './tip.store';")
    write_file(pf.CELER, 'src/stores/tip.store.ts')

    module = Path(tmp) / 'modulo'
    # `userStore` goes INSIDE the @Stores import block on purpose: it is the only path
    # that reaches the regex, and without it the `[A-Z]` test tests nothing — which is what
    # mutation showed. Outside the block, the import filter is already what protects it.
    write_file(module, 'Uso.vue', """
      import { useTipStore, userStore } from '@Stores';
      import { useUserStore } from '@UserModule';
      const tip = useTipStore();
      const usuario = useUserStore();
      const outra = useFantasmaStore();
    """)
    found = run_seam(pf.shared_stores, [module])

    check("a store from @Stores that exists passes", state(found, 'useTipStore') == pf.PRESENT)
    check("userStore imported from @Stores does NOT become a request for r.store.ts",
          not any('r.store.ts' in note for _, note in found.values()))
    check("a module store (@UserModule) is not charged against celer/src/stores",
          'useUserStore' not in found)
    check("a store used without coming from @Stores is not invented", 'useFantasmaStore' not in found)

# ═══════════════════════════════════════════════════════════════════════════
print("\n─── migration: does the model's table exist in the target? ───")

with tempfile.TemporaryDirectory() as tmp:
    pf.ARCUS = Path(tmp) / 'arcus'
    write_file(pf.ARCUS, 'database/migrations/2026_01_01_000001_create_tips_table.php')

    module = Path(tmp) / 'Modulo'
    write_file(module, 'Models/Tip.php', '<?php class Tip {}')
    write_file(module, 'Models/TipHistory.php', '<?php class TipHistory {}')
    write_file(module, 'Models/Aviso.php', "<?php class Aviso { protected $table = 'avisos_legados'; }")
    write_file(module, 'Services/TipService.php', '<?php class TipService {}')
    found = run_seam(pf.migrations, [module])

    check("a model whose migration exists passes", state(found, 'Tip → tips') == pf.PRESENT)
    check("a model with no migration is an absence", state(found, 'TipHistory → tip_histories') == pf.MISSING)
    check("a declared $table beats the automatic inflection", 'Aviso → avisos_legados' in found)
    check("a file outside Models/ is ignored",
          not any(item.startswith('TipService') for item in found))

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── front routes mounted in the router ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.CELER = Path(tmp) / 'celer'
    write_file(pf.CELER, 'src/libraries/router/routes.ts', "import { TipRoutes } from '@TipModule';")
    write_file(pf.CELER, 'src/libraries/router/routes.local.ts', '')

    mounted = Path(tmp) / 'src/modules/Tip'
    write_file(mounted, 'routes.ts')
    loose = Path(tmp) / 'src/modules/Fantasma'
    write_file(loose, 'routes.ts')
    without_routes = Path(tmp) / 'src/modules/Mudo'
    write_file(without_routes, 'index.ts')

    found = run_seam(pf.front_routes, [mounted, loose, without_routes])
    check("a module mounted in the router passes", state(found, 'TipRoutes') == pf.PRESENT)
    check("a module with routes and no import is an absence", state(found, 'FantasmaRoutes') == pf.MISSING)
    check("a module with no routes.ts is not charged", 'MudoRoutes' not in found)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── shared helper ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.CELER = Path(tmp) / 'celer'
    write_file(pf.CELER, 'src/helpers/index.ts',
               "export * from './i18n';\nexport { default as toggleBodyScrollLockHelper } from './bodyScrollLock.helper';")
    write_file(pf.CELER, 'src/helpers/i18n/index.ts', "export { getI18nRouteName } from './getI18nRouteName.helper';")

    module = Path(tmp) / 'modulo'
    write_file(module, 'Uso.vue',
               "import { getI18nRouteName, getLoginPath } from '@Helpers';\n"
               "import { toggleBodyScrollLockHelper } from '@Helpers';")

    found = run_seam(pf.shared_helpers, [module])
    check("a helper exported by a nested barrel passes", state(found, 'getI18nRouteName') == pf.PRESENT)
    check("a helper exported by the root barrel passes", state(found, 'toggleBodyScrollLockHelper') == pf.PRESENT)
    check("a helper the target does not export is an absence", state(found, 'getLoginPath') == pf.MISSING)
    check("a name that merely appears in prose is not charged", 'Helpers' not in found)

    # The composable check recognises a symbol by its `use` prefix, which is exactly why a plain
    # function slipped past it. This one must NOT inherit that assumption.
    write_file(module, 'Outro.vue', "import { formatMoney } from '@Helpers';")
    found = run_seam(pf.shared_helpers, [module])
    check("a helper with no `use` prefix is still checked", state(found, 'formatMoney') == pf.MISSING)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── css names the target has to define ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.CELER = Path(tmp) / 'celer'
    write_file(pf.CELER, 'src/styles/tailwind.css',
               "@utility motion-calm {\n  transition-duration: var(--motion-calm);\n}\n")
    write_file(pf.CELER, 'src/styles/theme/battoni.css', ":root {\n  --motion-calm: 320ms;\n}\n")

    child = Path(tmp) / 'filho'
    write_file(child, 'app.x/src/styles/tailwind.css',
               "@utility motion-calm {\n  transition-duration: var(--motion-calm);\n}\n"
               "@utility shadow-lift {\n  box-shadow: var(--relief-lift);\n}\n"
               "@utility motion-drift {\n  transition-duration: var(--motion-drift);\n}\n")

    module = Path(tmp) / 'modulo'
    write_file(module, 'Uso.vue',
               '<template>\n'
               '  <div class="motion-calm shadow-lift pb-[var(--mobile-nav-band)]" />\n'
               '  <div :style="{ gap: \'var(--motion-calm)\' }" />\n'
               '</template>')

    found = run_seam(lambda bases, rel: pf.css_names(child, bases, rel, 'app.x'), [module])

    check("a custom property the target defines passes", state(found, '--motion-calm') == pf.PRESENT)
    check("a custom property nothing defines is an absence",
          state(found, '--mobile-nav-band') == pf.MISSING)
    check("a hand-written utility the target declares passes", state(found, 'motion-calm') == pf.PRESENT)
    check("a hand-written utility the target lacks is an absence",
          state(found, 'shadow-lift') == pf.MISSING)
    # Only what the markup actually asks for: the source declares dozens of utilities and a report
    # naming all of them is a report nobody reads.
    check("a utility the source declares and the module never uses is not charged",
          'motion-drift' not in found)
    # Tailwind's own classes are not candidates — asking about `flex` would drown the report.
    check("a Tailwind class is not mistaken for a hand-written utility", 'pb-' not in found)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── shared composable, and what the shared component pulls ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.CELER = Path(tmp) / 'celer'
    write_file(pf.CELER, 'src/composables/index.ts', "export { useViewport } from './useViewport';")
    write_file(pf.CELER, 'src/stores/index.ts', "export * from './ui.store';")

    module = Path(tmp) / 'modulo'
    write_file(module, 'Uso.vue', """
      import { useViewport, useFantasma } from '@Composables';
      <template><MMainDialog /><OProprio /></template>
    """)
    write_file(module, 'OProprio.vue', '<template><div /></template>')
    found = run_seam(pf.shared_composables, [module])
    check("an existing composable passes", state(found, 'useViewport') == pf.PRESENT)
    check("a missing composable is an absence", state(found, 'useFantasma') == pf.MISSING)

    # Second level: the module imports none of this — the shared component does.
    child = Path(tmp) / 'filho'
    write_file(child, 'app.x/src/components/molecules/MMainDialog/MMainDialog.vue',
             "import { useViewport, useNovoNoPai } from '@Composables';\n"
             "import { useUiStore, useSumidaStore } from '@Stores';")
    found = run_seam(lambda bases, rel: pf.what_shared_components_pull(child, bases, rel), [module])

    check("a composable only the shared component pulls is seen",
          state(found, 'MMainDialog → useViewport') == pf.PRESENT)
    check("a store only the shared component pulls, present, passes",
          state(found, 'MMainDialog → useUiStore') == pf.PRESENT)
    check("a store only the shared component pulls, absent, is reported",
          state(found, 'MMainDialog → useSumidaStore') == pf.MISSING)
    check("a composable only the shared component pulls, absent, is reported",
          state(found, 'MMainDialog → useNovoNoPai') == pf.MISSING)
    check("the module's own component is not followed",
          not any(item.startswith('OProprio') for item in found))

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── who RECEIVES the permission is a decision, and has to be asked ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.ARCUS = Path(tmp) / 'arcus'
    write_file(pf.ARCUS, 'app/Modules/Auth/Helpers/PermissionGroupsHelper.php',
             "'tips.read', 'tips.manage'")
    write_file(pf.ARCUS, 'database/seeders/ManagerSeeder.php', "// nada sobre tips")

    PHP = "->middleware('permission:tips.read')->middleware('permission:tips.manage')"
    report = pf.Report()
    pf.permissions(PHP, report)

    # A dict is NO USE here: the two rows — the key itself and the role decision about it
    # — carry the same `item`, and one would swallow the other. The seam is what tells
    # them apart.
    por_costura = {(seam, item): est for seam, item, est, _ in report.rows}
    def under(seam, item):  # noqa: E306 — same reason as `state`: a vanished row is a failure, not a traceback
        return por_costura.get((seam, item), '—')
    notas = [note for *_, note in report.rows]

    check("a key declared in the helper passes",
          under('permission key', 'tips.read') == pf.PRESENT)
    check("a management key with no ManagerSeeder decision becomes a doubt",
          under('role receiving the permission', 'tips.manage') == pf.UNSURE)
    check("the key itself is still reported separately",
          under('permission key', 'tips.manage') == pf.PRESENT)
    check("the question about the role names ManagerSeeder",
          any('ManagerSeeder' in note for note in notas))
    check("a read key does not become a role question",
          ('role receiving the permission', 'tips.read') not in por_costura)

    write_file(pf.ARCUS, 'database/seeders/ManagerSeeder.php', "'tips.manage',")
    report = pf.Report()
    pf.permissions(PHP, report)
    check("a ManagerSeeder that already decides is not charged",
          all(estado_ != pf.UNSURE for _, _, estado_, _ in report.rows))

# ═══════════════════════════════════════════════════════════════════════════
print("\n─── the module seeder: does it exist, and is it called? ───")

with tempfile.TemporaryDirectory() as tmp:
    pf.ARCUS = Path(tmp) / 'arcus'
    write_file(pf.ARCUS, 'database/seeders/TipSeeder.php')
    write_file(pf.ARCUS, 'database/seeders/DatabaseSeeder.php', '$this->call(TipSeeder::class);')

    child = Path(tmp) / 'filho'
    write_file(child, 'api.x/database/seeders/TipSeeder.php')
    write_file(child, 'api.x/database/seeders/MudoSeeder.php')

    module = Path(tmp) / 'app/Modules/Tip'
    write_file(module, 'Models/Tip.php')
    found = run_seam(lambda bases, rel: pf.module_seeders(child, bases, rel), [module])
    check("a seeder present and called passes", state(found, 'TipSeeder.php') == pf.PRESENT)
    check("the DatabaseSeeder call is checked separately",
          state(found, 'TipSeeder called in DatabaseSeeder') == pf.PRESENT)

    write_file(pf.ARCUS, 'database/seeders/DatabaseSeeder.php', '// sem chamada nenhuma')
    found = run_seam(lambda bases, rel: pf.module_seeders(child, bases, rel), [module])
    check("a seeder that arrived and is never called is an absence",
          state(found, 'TipSeeder called in DatabaseSeeder') == pf.MISSING)

    mudo = Path(tmp) / 'app/Modules/Mudo'
    write_file(mudo, 'Models/Mudo.php')
    found = run_seam(lambda bases, rel: pf.module_seeders(child, bases, rel), [mudo])
    check("the source's seeder missing from the target is reported",
          state(found, 'MudoSeeder.php') == pf.MISSING)

    api = Path(tmp) / 'app/Modules/Tip'
    front = Path(tmp) / 'src/modules/Tip'
    write_file(front, 'index.ts')
    reported = rows(lambda bases, rel: pf.module_seeders(child, bases, rel), [api, front])
    check("API and front give the same module and it is not reported twice",
          reported.count('TipSeeder.php') == 1)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── nav entry: a module with a screen and no way to reach it ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.CELER = Path(tmp) / 'celer'
    write_file(pf.CELER, 'src/components/unique/TheNavbar/TheNavbar.vue',
             "{ label: 'navigation.tips', routeName: 'tips' },")
    write_file(pf.CELER, 'src/components/unique/TheNavbar/navItems.local.ts',
             "{ routeName: 'proprio' },")

    in_nav = Path(tmp) / 'Tip'
    write_file(in_nav, 'routes.ts', "{ name: 'tips-en' }, { name: 'tips-pt-BR' },")
    check("the stem matches despite the locale suffix",
          state(run_seam(pf.nav_entry, [in_nav]), 'Tip (1 rota(s))') == pf.PRESENT)

    local = Path(tmp) / 'Proprio'
    write_file(local, 'routes.ts', "{ name: 'proprio-en' },")
    check("navItems.local.ts counts too",
          state(run_seam(pf.nav_entry, [local]), 'Proprio (1 rota(s))') == pf.PRESENT)

    outside = Path(tmp) / 'Fantasma'
    write_file(outside, 'routes.ts', "{ name: 'fantasma-en' },")
    check("a module with a route and no menu entry is a doubt, not an absence",
          state(run_seam(pf.nav_entry, [outside]), 'Fantasma (1 rota(s))') == pf.UNSURE)

    sem = Path(tmp) / 'Mudo'
    write_file(sem, 'index.ts')
    check("a module with no routes.ts and no views is not charged", run_seam(pf.nav_entry, [sem]) == {})

    telas_soltas = Path(tmp) / 'Solto'
    write_file(telas_soltas, 'views/AlgumaView.view.vue')
    check("a module with views whose routes live elsewhere is still asked about",
          state(run_seam(pf.nav_entry, [telas_soltas]),
                 'Solto (telas, rotas declaradas fora)') == pf.UNSURE)

# ═══════════════════════════════════════════════════════════════════════════
print('\n─── test personas: signal, not noise ───')

with tempfile.TemporaryDirectory() as tmp:
    pf.ARCUS = Path(tmp) / 'arcus'
    write_file(pf.ARCUS, 'tests/Pest.php', '<?php\nfunction personaConhecida(): User {}\n')

    test_files = Path(tmp) / 'Feature'
    write_file(test_files, 'CoisaTest.php', """<?php
    // uma frase que cita toContain('x') sem chamar nada
    it('faz', function () {
        $u = personaConhecida();
        $outro = personaDesconhecida($this, ['a']);
        postJson('/api/x', ['body' => '<script>alert(1)</script>']);
        $partes = pathinfo('/tmp/z.png');
        foreach ([1, 2] as $n) { collect([$n])->first(); }
        expect($partes)->toContain('z');
    });
    """)
    found = run_seam(pf.test_helpers, [test_files])

    check("a persona that exists in Pest.php passes", state(found, 'personaConhecida()') == pf.PRESENT)
    check("a missing persona taking arguments is flagged", state(found, 'personaDesconhecida()') == pf.UNSURE)
    check("a PHP internal (pathinfo) does not become a persona", 'pathinfo()' not in found)
    check("a language construct (foreach) does not become a persona", 'foreach()' not in found)
    check("a Laravel helper (collect) does not become a persona", 'collect()' not in found)
    check("Pest's DSL (expect) does not become a persona", 'expect()' not in found)
    check("toContain quoted in a comment does not become a persona", 'toContain()' not in found)
    check("alert inside a string does not become a persona", 'alert()' not in found)

# ═══════════════════════════════════════════════════════════════════════════
print(f"\n{'PASS' if not failures else 'FAIL'} — {len(failures)} failure(s)")
for unit_name in failures:
    print(f'  ✗ {unit_name}')
sys.exit(1 if failures else 0)
