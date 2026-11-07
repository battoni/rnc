// Generates VitePress pages from the canonical rule and command files in
// `.claude/`. Single source of truth: rules and commands live at the monorepo
// root and are mirrored here so the handbook never drifts. Edit the source,
// not the output page.
//
// Output:
//   codelumen/rules/<project>/<slug>.md                  one page per rule
//   codelumen/commands/<name>.md                         one page per command
//   codelumen/.vitepress/generated/rules-sidebar.json    rule sidebar data
//   codelumen/.vitepress/generated/commands-sidebar.json command sidebar data
//
// `--check` writes nothing: it exits 1 when any generated file differs from what
// the sources produce now (a stale, missing or orphaned page), so CI can refuse
// a commit that edited a rule without regenerating the handbook.
//
// Deploy guard: if `.claude/rules` is not present (e.g. a Vercel build whose
// root excludes the parent dir), the script logs and exits 0, leaving the
// already-committed generated files untouched.

import { existsSync, mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const CODELUMEN = resolve(HERE, '..');
const RULES_SRC = resolve(CODELUMEN, '..', '.claude', 'rules');
const RULES_OUT = join(CODELUMEN, 'rules');
const SIDEBAR_OUT = join(CODELUMEN, '.vitepress', 'generated', 'rules-sidebar.json');

const COMMANDS_SRC = resolve(CODELUMEN, '..', '.claude', 'commands');
const COMMANDS_OUT = join(CODELUMEN, 'commands');
const COMMANDS_SIDEBAR_OUT = join(CODELUMEN, '.vitepress', 'generated', 'commands-sidebar.json');

const PROJECT_LABELS = {
  api.rnc: 'Arcus',
  app.rnc: 'app.rnc',
  shared: 'Shared',
};

const CHECK = process.argv.includes('--check');

// Every generated file, keyed by absolute path. `emit` collects instead of writing,
// so the same run can either write the tree or compare it.
const OUTPUT = new Map();

const ACRONYMS = { api: 'API', html: 'HTML', ts: 'TS', ui: 'UI', vue: 'Vue' };

// HELPERS

function emit(path, content) {
  OUTPUT.set(path, content);
}

function listFiles(directory) {
  if (!existsSync(directory)) return [];

  return readdirSync(directory, { recursive: true, withFileTypes: true })
    .filter((entry) => entry.isFile())
    .map((entry) => join(entry.parentPath ?? entry.path, entry.name));
}

// Compare what this run produced with what is on disk, under the generated roots.
function staleFiles(roots) {
  const stale = [];

  for (const [path, content] of OUTPUT) {
    const current = existsSync(path) ? readFileSync(path, 'utf8') : null;

    if (current !== content) stale.push(path);
  }

  for (const root of roots) {
    for (const path of listFiles(root)) {
      if (!OUTPUT.has(path)) stale.push(path);
    }
  }

  return stale;
}

function writeOutput(roots) {
  for (const root of roots) rmSync(root, { recursive: true, force: true });

  for (const [path, content] of OUTPUT) {
    mkdirSync(dirname(path), { recursive: true });
    writeFileSync(path, content);
  }
}

function parseFrontmatter(raw) {
  if (!raw.startsWith('---')) return { data: {}, body: raw };

  const end = raw.indexOf('\n---', 3);

  if (end === -1) return { data: {}, body: raw };

  const block = raw.slice(3, end).trim();
  const body = raw.slice(end + 4).replace(/^\n/, '');
  const data = {};

  for (const line of block.split('\n')) {
    const match = line.match(/^(\w+):\s*(.*)$/);

    if (!match) continue;

    const [, key, value] = match;

    data[key] = value.trim();
  }

  return { data, body };
}

function parseGlobs(value) {
  if (!value) return [];

  try {
    return JSON.parse(value);
  } catch {
    return [value.replace(/^["']|["']$/g, '')];
  }
}

function titleCase(slug) {
  return slug
    .replace(/^\d+-/, '')
    .split('-')
    .map((word) => ACRONYMS[word] ?? word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function splitName(filename) {
  const base = filename.replace(/\.md$/, '');
  const project = base.slice(0, base.indexOf('-'));
  const slug = base.slice(base.indexOf('-') + 1);

  return { project, slug };
}

// VitePress compiles markdown as a Vue template, so a bare `<word>` in prose
// (e.g. a `<project>` placeholder) is read as an unclosed tag and breaks the
// build. Escape `<` everywhere except inside fenced or inline code, where it is
// already literal. `>` is left alone so blockquotes keep working.
//
// `{{ }}` is the same problem with a different answer, and that difference is what
// broke the build: a mustache inside INLINE code is NOT literal. The template is
// compiled from the rendered HTML, so `<code>{{ t('x') }}</code>` is still an
// interpolation — and celer-01-vue-checklist, which quotes `{{ t('key') }}` in a
// table to say never to write it, failed with "_ctx.t is not a function". Fenced
// blocks are safe; inline spans are not, so mustaches are escaped on every line.
function escapeAngles(body) {
  let inFence = false;

  return body
    .split('\n')
    .map((line) => {
      if (/^\s*```/.test(line)) {
        inFence = !inFence;
        return line;
      }

      if (inFence) return line;

      return line
        .replace(/\{\{/g, '&#123;&#123;')
        .replace(/(`[^`]*`)|</g, (match, code) => (code ? code : '&lt;'));
    })
    .join('\n');
}

function activationLine(data) {
  const globs = parseGlobs(data.globs);

  if (data.alwaysApply === 'true' || globs.length === 0) return 'Always active across the monorepo.';

  return `Activates for: ${globs.map((glob) => `\`${glob}\``).join(', ')}.`;
}

function renderPage(filename, data, body) {
  const sourceRef = `.claude/rules/${filename}`;
  const title = data.description ?? titleCase(splitName(filename).slug);

  return `---
title: ${title}
outline: deep
---

::: info Generated page
This page is generated from \`${sourceRef}\` — **edit the source rule, not this page.** ${activationLine(data)}
:::

${escapeAngles(body).trimEnd()}
`;
}

function renderCommandPage(filename, body) {
  const name = filename.replace(/\.md$/, '');

  return `---
title: /${name}
outline: deep
---

::: info Generated page
This page is generated from \`.claude/commands/${filename}\` — run it with \`/${name}\`. **Edit the source command, not this page.**
:::

${escapeAngles(body).trimEnd()}
`;
}

function generateCommands() {
  if (!existsSync(COMMANDS_SRC)) return 0;

  const files = readdirSync(COMMANDS_SRC)
    .filter((name) => name.endsWith('.md'))
    .sort();

  const sidebar = [];

  for (const filename of files) {
    const raw = readFileSync(join(COMMANDS_SRC, filename), 'utf8');
    const { body } = parseFrontmatter(raw);
    const name = filename.replace(/\.md$/, '');

    emit(join(COMMANDS_OUT, filename), renderCommandPage(filename, body));
    sidebar.push({ text: `/${name}`, link: `/commands/${name}` });
  }

  emit(COMMANDS_SIDEBAR_OUT, `${JSON.stringify(sidebar, null, 2)}\n`);

  return sidebar.length;
}

// MAIN

function main() {
  if (!existsSync(RULES_SRC)) {
    console.warn(`[generate-rules] ${RULES_SRC} not found — keeping committed output.`);
    process.exit(0);
  }

  const files = readdirSync(RULES_SRC)
    .filter((name) => name.endsWith('.md'))
    .sort();

  const sidebar = {};

  for (const filename of files) {
    const { project, slug } = splitName(filename);

    if (!PROJECT_LABELS[project]) {
      console.warn(`[generate-rules] skipping ${filename} — unknown project prefix.`);
      continue;
    }

    const raw = readFileSync(join(RULES_SRC, filename), 'utf8');
    const { data, body } = parseFrontmatter(raw);

    emit(join(RULES_OUT, project, `${slug}.md`), renderPage(filename, data, body));

    sidebar[project] ??= [];
    sidebar[project].push({ text: titleCase(slug), link: `/rules/${project}/${slug}` });
  }

  emit(SIDEBAR_OUT, `${JSON.stringify(sidebar, null, 2)}\n`);

  const count = Object.values(sidebar).reduce((total, items) => total + items.length, 0);
  const commandCount = generateCommands();
  const roots = existsSync(COMMANDS_SRC) ? [RULES_OUT, COMMANDS_OUT] : [RULES_OUT];

  if (CHECK) {
    const stale = staleFiles(roots);

    if (stale.length > 0) {
      console.error('[generate-rules] generated pages are out of date — run `make docs-gen`:');

      for (const path of stale) console.error(`  - ${path.slice(CODELUMEN.length + 1)}`);

      process.exit(1);
    }

    console.log('[generate-rules] generated pages are current.');
    return;
  }

  writeOutput(roots);

  console.log(
    `[generate-rules] wrote ${count} rule pages across ${Object.keys(sidebar).length} projects, and ${commandCount} command pages.`,
  );
}

main();
