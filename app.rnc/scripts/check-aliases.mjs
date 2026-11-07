#!/usr/bin/env node
/**
 * A module alias has to be declared in THREE files, and forgetting one fails somewhere else.
 *
 * `vite.config.ts` serves the build and the dev server, `tsconfig.app.json` serves the editor
 * and `type-check`, and `vitest.config.ts` serves the suite — deliberately standalone, so unit
 * tests do not drag devtools and tailwind into jsdom. That independence is the right call, and
 * it is exactly what lets the three drift.
 *
 * Miss one and the failure lands somewhere unrelated to the edit: the build is fine and the
 * suite cannot resolve the import, or the suite is green and `type-check` denies the module
 * exists. The symptom never points at the cause.
 *
 * The warning already existed, as a comment at the top of `vitest.config.ts`. A comment is read
 * by whoever is already in that file, which is not the person adding an alias to the other two.
 * This is that comment as an exit code.
 *
 * WHY A SCRIPT AND NOT A SPEC. The first version was a Vitest spec. Importing `vite.config.ts`
 * pulls esbuild in, and esbuild refuses to run under jsdom — `new TextEncoder().encode('')`
 * returns a Uint8Array from another realm and its own invariant check fails. It reports "your
 * JavaScript environment is broken", naming neither the file nor the import. `@vitest-environment
 * node` did not rescue it. This repo already had the right shape for a config-level invariant:
 * a plain node script wired into CI (the shape the retired features-catalogue check had).
 *
 * It reads the RESOLVED configs through vite's own loader, never their source text. Two attempts
 * to compare these three by parsing strings reported drift that did not exist — `vitest.config.ts`
 * builds its aliases from two separate lists, and a regex over the file sees one of them.
 *
 * Usage: node scripts/check-aliases.mjs
 */
import { readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadConfigFromFile } from 'vite';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const ENV = { command: 'build', mode: 'production' };

// Only `@Name` aliases are the contract. A regex `find` is an internal test stub, and a
// trailing `/*` is the same alias in its wildcard spelling.
const isContractAlias = (name) => typeof name === 'string' && /^@[A-Z]/.test(name);
const bare = (name) => name.replace(/\/\*$/, '');

// An alias that resolves to a STYLESHEET is vite's alone, and its absence elsewhere is
// correct rather than drift. `@ActiveTheme` is reached from one `@import` inside
// `colors.css`: the suite runs with `css: false` so it never resolves it, and TypeScript
// does not resolve module specifiers inside a `.css` file either. Demanding it in all
// three would mean adding two declarations that nothing can ever read — which teaches
// whoever reads them that this check does not know what it is talking about.
const isStylesheet = (target) => typeof target === 'string' && /\.(css|scss|sass|less)$/.test(target);

const collect = (pairs) =>
  new Set(pairs.filter(([name, target]) => isContractAlias(name) && !isStylesheet(target)).map(([name]) => bare(name)));

async function aliasesFromViteConfig(file) {
  const loaded = await loadConfigFromFile(ENV, resolve(ROOT, file), ROOT);
  const alias = loaded?.config?.resolve?.alias ?? {};
  const pairs = Array.isArray(alias) ? alias.map((entry) => [entry.find, entry.replacement]) : Object.entries(alias);

  return collect(pairs);
}

function aliasesFromTsconfig(file) {
  // JSONC: comments and trailing commas are legal in a tsconfig.
  const source = readFileSync(resolve(ROOT, file), 'utf8')
    .replace(/^\s*\/\/.*$/gm, '')
    .replace(/,(\s*[}\]])/g, '$1');

  const paths = JSON.parse(source).compilerOptions?.paths ?? {};

  // A tsconfig path maps to a LIST of targets; the first is enough to tell a stylesheet.
  return collect(Object.entries(paths).map(([name, targets]) => [name, targets?.[0]]));
}

const missing = (from, against) => [...from].filter((name) => !against.has(name)).sort();

const vite = await aliasesFromViteConfig('vite.config.ts');
const vitest = await aliasesFromViteConfig('vitest.config.ts');
const tsconfig = aliasesFromTsconfig('tsconfig.app.json');

const problems = [];

// A parser that quietly returns nothing passes every comparison below. Refuse that first.
for (const [name, set] of [
  ['vite.config.ts', vite],
  ['vitest.config.ts', vitest],
  ['tsconfig.app.json', tsconfig],
]) {
  if (set.size < 10) {
    problems.push(`${name}: read ${set.size} aliases — the check cannot have worked.`);
  }
}

if (problems.length === 0) {
  for (const [have, want, consequence] of [
    [vitest, vite, 'the suite cannot resolve the import'],
    [tsconfig, vite, 'type-check denies the module exists'],
    // The other direction matters too: an alias the tests resolve and the build does not is a
    // green suite in front of a broken deploy.
    [vite, vitest, 'the build cannot resolve what the suite can'],
    [vite, tsconfig, 'the build cannot resolve what the editor can'],
  ]) {
    for (const name of missing(want, have)) {
      problems.push(`${name} is missing — ${consequence}.`);
    }
  }
}

if (problems.length > 0) {
  console.error('Module aliases disagree across the files that declare them:\n');
  for (const problem of [...new Set(problems)]) console.error(`  ${problem}`);
  console.error('\nDeclare it in vite.config.ts, tsconfig.app.json AND vitest.config.ts.');
  process.exit(1);
}

console.log(`Module aliases agree — ${vite.size} declared in all three.`);
