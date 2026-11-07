import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite';
import vue from '@vitejs/plugin-vue';
import { resolve, dirname } from 'node:path';
import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vitest/config';

// Aliases mirror vite.config.ts. Kept standalone (not importing vite.config) so unit
// tests don't pull in devtools/tailwind/auto-import plugins under jsdom.
//
// An alias has to be declared HERE, in vite.config.ts and in tsconfig.app.json, and this
// note used to be the only thing saying so — read by whoever was already in this file,
// which is never the person adding an alias to the other two. `npm run check:aliases`
// asserts the three agree and CI runs it; see `scripts/check-aliases.mjs` for why a script
// and not a spec. It found five aliases declared only here, one of them (`@Router`)
// pointing at a directory that does not exist.

// Router stub must be listed FIRST so it takes precedence over the general @/ alias.
// This prevents routes.ts from spreading uninitialized module barrel exports in tests.
const routerStub = fileURLToPath(new URL('./src/test/mocks/router.stub.ts', import.meta.url));
// Exact-match alias for the @Libraries barrel prevents libraries/index.ts from loading
// './router' (relative, bypasses alias) which triggers the routes.ts circular-dep error.
const librariesBarrelStub = fileURLToPath(new URL('./src/test/mocks/libraries-barrel.stub.ts', import.meta.url));

const aliasEntries = [
  // Specific stubs — must appear before the generic @/ and @Libraries prefixes.
  { find: /^@\/libraries\/router(\/|$)/, replacement: routerStub },
  { find: /^@Libraries\/router(\/|$)/, replacement: routerStub },
  { find: /^@Libraries$/, replacement: librariesBarrelStub },
  { find: '@', replacement: fileURLToPath(new URL('./src', import.meta.url)) },
];

const moduleAliases = [
  'Assets',
  'Composables',
  'Constants',
  'Enums',
  'Helpers',
  'Interfaces',
  'Libraries',
  'Plugins',
  'Providers',
  'Services',
  'Stores',
  'Styles',
  'Types',
];

moduleAliases.forEach((moduleName) => {
  aliasEntries.push({
    find: `@${moduleName}`,
    replacement: fileURLToPath(new URL(`./src/${moduleName.toLowerCase()}`, import.meta.url)),
  });
});

const moduleSubAliases = [
  { alias: 'AuthModule', path: './src/modules/Auth' },
  { alias: 'HomeModule', path: './src/modules/Home' },
  { alias: 'IdeaModule', path: './src/modules/Idea' },
  { alias: 'PersonalizationModule', path: './src/modules/Personalization' },
  { alias: 'ShowcaseModule', path: './src/modules/Showcase' },
  { alias: 'TipModule', path: './src/modules/Tip' },
  { alias: 'UserModule', path: './src/modules/User' },
];

moduleSubAliases.forEach(({ alias, path }) => {
  aliasEntries.push({
    find: `@${alias}`,
    replacement: fileURLToPath(new URL(path, import.meta.url)),
  });
});

export default defineConfig({
  plugins: [
    vue(),
    VueI18nPlugin({
      // `*.json`, exactly as vite.config.ts — NOT `**`. The plugin precompiles every file its
      // glob matches, and `**` reached `src/locales/local/*.json` too. Those overrides must arrive
      // as plain strings, because `src/libraries/i18n/index.ts` deep-merges them over the core
      // AFTER the plugin runs; precompiled, any local message carrying a `{param}` fails in
      // vue-i18n's message evaluation the moment it is translated — in every spec that mounts.
      //
      // It never fired here only because rnc's `local/*.json` are `{}`. A project's first
      // parameterised override would have been its first red suite, with a stack trace inside
      // `@intlify/core-base` naming neither this file nor the glob. Reproduced before fixing:
      // a local `Hello {name}` and a local override of `errors.unexpected` both fail under `**`
      // and pass under `*.json`. Found by chapaja, where the wallet.* keys hit it.
      include: resolve(dirname(fileURLToPath(import.meta.url)), './src/locales/*.json'),
    }),
  ],
  resolve: {
    alias: aliasEntries,
  },
  test: {
    // Co-located unit specs live beside their unit: src/**/<Unit>.spec.ts
    // Integration specs use the same pattern with the .integration.spec.ts suffix.
    include: ['src/**/*.spec.ts'],
    env: {
      // Gives axios an absolute base so MSW can intercept the requests.
      VITE_API_URL: 'http://localhost',
      // Pin the UI language so specs asserting rendered i18n text are deterministic.
      // Without this, locale comes from a gitignored .env locally (en) but falls back to
      // the 'pt-BR' default on CI — which made CI red while local passed. See env.constant.ts.
      VITE_DEFAULT_LANGUAGE: 'en',
    },
    exclude: ['node_modules', 'dist', 'e2e'],
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./src/test/setup.ts'],
    css: false,
    coverage: {
      provider: 'v8',
      include: ['src/**/*.{ts,vue}'],
      exclude: ['src/**/*.spec.ts', 'src/test/**', 'src/**/index.ts', 'src/**/*.d.ts'],
    },
  },
});
