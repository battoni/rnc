#!/usr/bin/env node
/**
 * Reads the per-unit metadata layer and writes the catalogue the Features screen renders.
 *
 * The source of truth is ~80 `RULES.md` + `CHANGELOG.md` pairs spread through the tree, and
 * none of them reach the browser: they are repository files, not modules. So the catalogue is
 * GENERATED here and committed, the same bargain `components.d.ts` already makes — the screen
 * can never disagree with the repo, and costs nothing at runtime.
 *
 * Grouping is INFERRED from the path, never maintained by hand:
 *   app.rnc/src/modules/<X>/**  and  api.rnc/app/Modules/<X>/**  ->  feature <X>
 *   everything else                                          ->  the shared base
 *
 * A unit whose RULES.md declares `feature: <name>` in the frontmatter overrides the inference.
 * That key does not exist yet and should stay unused until a feature genuinely spans units
 * that do not share its name — a hand-maintained list rots the first week somebody is in a
 * hurry, so we only pay for the declaration where the path cannot answer.
 *
 * Usage:  node app.rnc/scripts/build-features.mjs [--check]
 *         --check exits 1 when the committed file is stale, for CI.
 */
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = join(fileURLToPath(new URL('.', import.meta.url)), '..', '..');
// Beside its only consumer, on purpose: no path alias to add, no config file to touch in
// four places, and the view that reads it is the view that breaks when it goes missing.
const DESTINO = join(
  ROOT, 'app.rnc', 'src', 'modules', 'Showcase', 'views', 'Features', 'features.generated.json',
);
const ARVORES = [join('app.rnc', 'src'), join('api.rnc', 'app')];
const IGNORAR = new Set(['node_modules', 'vendor', 'dist', '.git']);

function encontrarContratos(base) {
  const achados = [];

  function caminhar(diretorio) {
    for (const entry of readdirSync(diretorio)) {
      if (IGNORAR.has(entry)) continue;

      const path = join(diretorio, entry);
      if (statSync(path).isDirectory()) {
        caminhar(path);
        continue;
      }

      if (entry === 'RULES.md') achados.push(path);
    }
  }

  caminhar(join(ROOT, base));
  return achados;
}

/** Frontmatter YAML raso, a partir do text. Aspas em volta do value caem. */
function frontmatterDe(text) {
  const bloco = text.match(/^---\n([\s\S]*?)\n---/);
  if (!bloco) return {};

  return Object.fromEntries(
    bloco[1]
      .split('\n')
      .map((line) => line.match(/^([\w-]+):\s*(.+)$/))
      .filter(Boolean)
      .map((found) => [found[1], found[2].trim().replace(/^["']|["']$/g, '')]),
  );
}

function lerFrontmatter(path) {
  return frontmatterDe(readFileSync(path, 'utf8'));
}

/** The CHANGELOG is canonical for the version; RULES.md frontmatter only mirrors it. */
function lerChangelog(diretorio) {
  try {
    const text = readFileSync(join(diretorio, 'CHANGELOG.md'), 'utf8');
    const topo = text.match(/^##\s+(\d+\.\d+\.\d+)\s+—\s+(\S+)/m);
    if (!topo) return {};

    return { version: topo[1], date: topo[2] };
  } catch {
    return {};
  }
}

function nomeDaFeature(relativo, declarado) {
  if (declarado) return declarado;

  const parts = relativo.split(sep);
  const modulos = parts.indexOf('modules') !== -1 ? 'modules' : 'Modules';
  const index = parts.indexOf(modulos);

  return index !== -1 && parts[index + 1] ? parts[index + 1] : 'base';
}

/*
 * The tooling layer — `.claude/` and `cortex/` — is a big part of what a project receives and
 * none of it is a "unit": no RULES.md, no CHANGELOG, no version. Giving each skill a metadata
 * triad would be bureaucracy for its own sake, so this reads what each kind ALREADY declares:
 * frontmatter where there is frontmatter, the first heading where there is not, the first real
 * comment in a shell script. A row here is a name and a sentence, which is all a catalogue owes.
 */
const TOOLING = [
  { group: 'skill', dir: '.claude/skills', file: 'SKILL.md', reader: 'frontmatter' },
  { group: 'agent', dir: '.claude/agents', reader: 'frontmatter' },
  { group: 'command', dir: '.claude/commands', reader: 'heading' },
  { group: 'rule', dir: '.claude/rules', reader: 'heading' },
  { group: 'hook', dir: '.claude/hooks', reader: 'comment' },
  { group: 'cortex', dir: 'cortex', reader: 'subsystem' },
];

function firstHeading(text) {
  const found = text.match(/^#\s+(.+)$/m);

  return found ? found[1].trim() : null;
}

/*
 * A skill's `description` is written to route the MODEL: it opens with what the thing does and
 * then lists every trigger phrase that should load it. The first sentence is the catalogue row;
 * the rest is machinery, and printing it turns the section into a wall.
 */
function firstSentence(text) {
  if (!text) return null;

  const cleaned = text.replace(/\s+/g, ' ').trim();
  const end = cleaned.search(/\.\s|\.$|—/);

  return end === -1 ? cleaned : cleaned.slice(0, end + 1).replace(/[—.]$/, '').trim();
}

/** The first comment line that is not the shebang. */
function firstComment(text) {
  const line = text.split('\n').find((current) => /^#[^!]/.test(current));

  return line ? line.replace(/^#\s*/, '').trim() : null;
}

function readTooling() {
  const rows = [];

  for (const { group, dir, file, reader } of TOOLING) {
    const base = join(ROOT, dir);
    let entries;
    try {
      entries = readdirSync(base);
    } catch {
      continue;
    }

    for (const entry of entries.sort()) {
      const path = join(base, entry);
      const isDirectory = statSync(path).isDirectory();

      if (reader === 'subsystem') {
        if (!isDirectory) continue;

        // A subsystem is ONE row, not a list of its files: `cortex/sync` is a mechanism,
        // and enumerating its fifteen files says no more than the name does.
        const docs = readdirSync(path).filter((name) => name.endsWith('.md')).sort();
        const doc = docs.find((name) => name === 'DESIGN.md') ?? docs[0];

        rows.push({
          group: group,
          name: `cortex/${entry}`,
          description: doc ? firstHeading(readFileSync(join(path, doc), 'utf8')) : null,
          path: `cortex/${entry}`,
        });
        continue;
      }

      const target = isDirectory ? join(path, file ?? 'SKILL.md') : path;
      let content;
      try {
        content = readFileSync(target, 'utf8');
      } catch {
        continue;
      }

      const name = isDirectory ? entry : entry.replace(/\.\w+$/, '');
      let descricao = null;

      if (reader === 'frontmatter') descricao = frontmatterDe(content).description ?? null;
      if (reader === 'heading') descricao = firstHeading(content);
      if (reader === 'comment') descricao = firstComment(content);

      rows.push({
        group: group,
        name: name,
        description: firstSentence(descricao),
        path: `${dir}/${entry}`,
      });
    }
  }

  return rows;
}

function montar() {
  const units = [];

  for (const arvore of ARVORES) {
    for (const contrato of encontrarContratos(arvore)) {
      const diretorio = contrato.slice(0, -'/RULES.md'.length);
      const relativo = relative(ROOT, diretorio);
      const frontmatter = lerFrontmatter(contrato);
      const changelog = lerChangelog(diretorio);

      units.push({
        path: relativo.split(sep).join('/'),
        feature: nomeDaFeature(relativo, frontmatter.feature),
        side: arvore.startsWith('app.rnc') ? 'app' : 'api',
        version: changelog.version ?? frontmatter.version ?? null,
        origin: frontmatter.origin ?? null,
        basedOn: frontmatter['based-on'] ?? null,
        // The gate keeps these equal; when they drift, the unit is mid-edit and the screen
        // should say so rather than quietly pick one.
        mirrorsChangelog: !changelog.version || changelog.version === frontmatter.version,
        updatedAt: changelog.date ?? null,
      });
    }
  }

  const porFeature = new Map();
  for (const unit of units.sort((first, second) => first.path.localeCompare(second.path))) {
    if (!porFeature.has(unit.feature)) porFeature.set(unit.feature, []);
    porFeature.get(unit.feature).push(unit);
  }

  const features = [...porFeature.entries()]
    .map(([name, lista]) => ({
      name: name,
      sides: [...new Set(lista.map((unit) => unit.side))].sort(),
      unitCount: lista.length,
      // A feature has no version of its own; it has the versions of its units. Showing the
      // newest date is honest — showing a single "feature version" would be invented.
      lastChangedAt: lista.map((u) => u.updatedAt).filter(Boolean).sort().pop() ?? null,
      units: lista,
    }))
    .sort((first, second) => {
      if (first.name === 'base') return 1;
      if (second.name === 'base') return -1;

      return first.name.localeCompare(second.name);
    });

  const tooling = readTooling();

  return {
    generatedFrom: 'rnc',
    unitCount: units.length,
    features,
    toolingCount: tooling.length,
    tooling,
  };
}

const CATALOGUE = JSON.stringify(montar(), null, 2) + '\n';

if (process.argv.includes('--check')) {
  const current = (() => {
    try {
      return readFileSync(DESTINO, 'utf8');
    } catch {
      return '';
    }
  })();

  if (current !== CATALOGUE) {
    console.error('features.generated.json is out of date — run `npm run build:features`.');
    process.exit(1);
  }

  console.log('features.generated.json is current.');
  process.exit(0);
}

writeFileSync(DESTINO, CATALOGUE);
console.log(`${JSON.parse(CATALOGUE).features.length} features · ${JSON.parse(CATALOGUE).unitCount} units -> ${relative(ROOT, DESTINO)}`);
