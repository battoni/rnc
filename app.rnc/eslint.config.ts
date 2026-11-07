import skipFormatting from '@vue/eslint-config-prettier/skip-formatting';
import { defineConfigWithVueTs, vueTsConfigs } from '@vue/eslint-config-typescript';
import jsoncPlugin from 'eslint-plugin-jsonc';
import perfectionist from 'eslint-plugin-perfectionist';
import pluginVue from 'eslint-plugin-vue';
import { globalIgnores } from 'eslint/config';

// ── Design-system colour guards ──────────────────────────────────────────────
// The rules are written in `.claude/rules/celer-06-design-system.md` and in
// `src/styles/theme/RULES.md`. These turn the three most-violated ones into ERRORS,
// because a rule that lives only in a document does not hold: a component painted
// `bg-slate-100` lints clean, reads fine in light, and simply fails to flip for dark —
// nothing fails, it is just wrong for half the users.
//
// All three sit at zero occurrences today, so they are regression guards rather than a
// migration. From lifeshifter, which wrote them after the audit that found the drift.
//
// Deliberately NOT banned: a bare hex in script or in a `:style` binding. A colour that is
// DATA — chosen by a person, stored, arbitrary — cannot be a token and does not belong to
// the theme. Only a hex used as *styling* is wrong, which in Tailwind means an arbitrary
// class value like `bg-[#fff]`. The naive version of this rule flags the data case too, and
// is switched off the week after.
const COLOUR_UTILITIES =
  'bg|text|border|ring|fill|stroke|from|via|to|divide|outline|decoration|accent|caret|placeholder';

const TAILWIND_PALETTES =
  'gray|slate|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose';

const NATIVE_PALETTE = `\\b(${COLOUR_UTILITIES})-(${TAILWIND_PALETTES})-(50|[1-9]00|950)\\b`;
const ABSOLUTE_COLOUR = `\\b(${COLOUR_UTILITIES})-(white|black)\\b`;
const ARBITRARY_HEX = `\\b(${COLOUR_UTILITIES})-\\[#[0-9a-fA-F]{3,8}\\]`;

const COLOUR_GUARDS = [
  {
    pattern: NATIVE_PALETTE,
    message:
      'Built-in Tailwind palettes do not flip for dark. Use a role token (bg-canvas, bg-panel, text-heading, text-muted, border-line) for structure, a state palette (success/info/warn/danger/help/contrast) for meaning, or primary-* for brand action.',
  },
  {
    pattern: ABSOLUTE_COLOUR,
    message:
      'bg-white / text-black are absolute and do not flip for dark. Use bg-canvas / bg-panel / text-heading.',
  },
  {
    pattern: ARBITRARY_HEX,
    message:
      'A hex used as styling escapes the theme. Use a role token, a state palette, or primary-*. A colour that is DATA belongs in a :style binding, not in a class.',
  },
];

// `vue/no-restricted-syntax` walks the template AST: VLiteral covers a static class="…",
// Literal covers every string inside :class="[…]" and friends.
const TEMPLATE_COLOUR_GUARDS = COLOUR_GUARDS.flatMap(({ pattern, message }) => [
  { selector: `VLiteral[value=/${pattern}/]`, message },
  { selector: `Literal[value=/${pattern}/]`, message },
]);

// Plain `no-restricted-syntax` walks the script block, where computed class lists live.
// Template literals need their own node type or a `bg-${x}-100` slips past.
const SCRIPT_COLOUR_GUARDS = COLOUR_GUARDS.flatMap(({ pattern, message }) => [
  { selector: `Literal[value=/${pattern}/]`, message },
  { selector: `TemplateElement[value.raw=/${pattern}/]`, message },
]);


export default defineConfigWithVueTs(
  {
    name: 'app/files-to-lint',
    files: ['**/*.{ts,mts,tsx,vue}'],
  },

  globalIgnores([
    '**/dist/**',
    '**/dist-ssr/**',
    '**/coverage/**',
    '**/playwright-report/**',
    '**/test-results/**',
    '**/playwright/.cache/**',
  ]),

  pluginVue.configs['flat/essential'],
  vueTsConfigs.recommended,
  skipFormatting,

  {
    plugins: {
      perfectionist,
    },
    rules: {
      // ── Hygiene ───────────────────────────────────────────────────────────────
      // The project rule already banned console in committed code and nothing enforced it:
      // nine `console.log(payload)` lived in the bootstrap's Auth services, four of them
      // printing a person's PASSWORD to the browser console — and three of the four children
      // inherited them. Banned for good; the one deliberate call left carries a disable
      // comment in `errorHandler.helper.ts`.
      'no-console': 'error',

      // `_payload` in a mock: the signature is the contract the real implementation will
      // have, so the argument genuinely goes unused. The underscore makes that deliberate and
      // visible instead of silencing the rule altogether.
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],

      /*
       * A module is imported by its root alias, never by a path inside it.
       *
       * `celer-02-vue-imports` has claimed this is "enforced by ESLint `no-restricted-imports`"
       * since the bootstrap was written, and the rule did not exist — not here and not in any of
       * the four children. Nobody had broken it, so the convention was living on discipline
       * while the documentation promised a guard. A rule that is documented and absent is worse
       * than one that is merely documented: it is the reason nobody checks.
       *
       * The barrel is the module's public surface. A deep import reaches past it, binds to a
       * file path instead of a contract, and is the thing that makes a module impossible to
       * rearrange later. The fix when this fires is to export the symbol from the module's
       * `index.ts` or `interfaces.ts` — never to add the path here.
       */
      'no-restricted-imports': [
        'error',
        {
          patterns: [
            {
              group: ['@*Module/*'],
              message:
                'Import from the module root (e.g. @AuthModule), never a path inside it. If the symbol is missing, add it to the module barrel.',
            },
          ],
        },
      ],

      // ── TypeScript ────────────────────────────────────────────────────────────
      '@typescript-eslint/no-explicit-any': 'off',

      // Enforce `import type` syntax for type-only imports
      '@typescript-eslint/consistent-type-imports': [
        'error',
        { prefer: 'type-imports', fixStyle: 'separate-type-imports' },
      ],

      // Enums must be UPPER_CASE (not PascalCase, which looks like an interface)
      '@typescript-eslint/naming-convention': [
        'error',
        { selector: 'enum', format: ['UPPER_CASE'] },
      ],

      // ── Imports ───────────────────────────────────────────────────────────────
      'perfectionist/sort-imports': [
        'error',
        {
          type: 'alphabetical',
          order: 'asc',
          newlinesBetween: 0,
          groups: [
            'vue',
            'external',
            // All type imports grouped together between external and internal
            'all-types',
            'internal',
            'modules',
            ['parent', 'sibling', 'index'],
          ],
          customGroups: [
            { groupName: 'vue', elementNamePattern: '^vue$' },
            // all-types must come before path-based groups so it wins for type imports
            { groupName: 'all-types', selector: 'type' },
            { groupName: 'internal', elementNamePattern: '^@[A-Z](?!\\w+Module)' },
            { groupName: 'modules', elementNamePattern: '^@[A-Z]\\w+Module' },
          ],
        },
      ],

      // ── Logic ─────────────────────────────────────────────────────────────────
      // No `else` after a return — use early returns instead
      'no-else-return': 'error',

      // Catch blocks must not be empty
      'no-empty': ['error', { allowEmptyCatch: false }],

      // ── Vue template ──────────────────────────────────────────────────────────
      'vue/v-bind-style': ['error', 'shorthand', { sameNameShorthand: 'always' }],

      // Templates must use the global `$t()`, never the script-scoped `t()`.
      // `vue/no-restricted-syntax` only inspects <template>, so script-side
      // `t()` (yup resolvers, toasts) is unaffected — import `t` only when the
      // script itself needs it.
      'vue/no-restricted-syntax': [
        'error',
        {
          selector: "CallExpression[callee.name='t']",
          message: 'Use $t() in templates, not t(). Reserve t()/useI18n for the <script> block.',
        },
        ...TEMPLATE_COLOUR_GUARDS,
      ],

      'vue/attributes-order': [
        'error',
        {
          order: [
            'LIST_RENDERING',           // v-for
            'CONDITIONALS',             // v-if, v-else-if, v-else, v-show
            'RENDER_MODIFIERS',         // v-pre, v-once
            'SLOT',                     // v-slot
            'TWO_WAY_BINDING',          // v-model, v-model:visible
            'OTHER_DIRECTIVES',         // other directives (v-tooltip, etc.)
            'ATTR_SHORTHAND_BOOL',      // fluid, plain, text (boolean, no value)
            'GLOBAL',                   // id only (before other static attrs)
            ['ATTR_STATIC', 'UNIQUE'],  // class, key, mask, name, pt:body="...", etc. — all ASC alphabetically
            'ATTR_DYNAMIC',             // :class, :id, :modelValue (bound, all ASC)
            'EVENTS',                   // @click, @submit
            'CONTENT',                  // v-html, v-text
          ],
          alphabetical: true,
        },
      ],
    },
  },

  // ── Command-line scripts ───────────────────────────────────────────────────
  // `console.log` here is NOT leftover debugging: it is the program's output. The ban
  // exists for code that runs in someone else's browser, not for a script somebody ran in
  // a terminal expecting to read the result.
  {
    files: ['scripts/**/*.{mjs,js,ts}'],
    rules: {
      'no-console': 'off',
    },
  },

  // ── Locale JSON files ──────────────────────────────────────────────────────
  {
    name: 'app/locales',
    files: ['src/locales/**/*.json'],
    plugins: { jsonc: jsoncPlugin },
    languageOptions: { parser: jsoncPlugin },
    rules: {
      '@typescript-eslint/consistent-type-imports': 'off',
      '@typescript-eslint/naming-convention': 'off',
      'jsonc/no-dupe-keys': 'error',
      'jsonc/valid-json-number': 'error',
    },
  },

  {
    name: 'app/colour-guards',
    files: ['src/**/*.{ts,vue}'],
    rules: {
      // Script side — computed class lists live there. Scoped to `src/` so THIS file, which
      // quotes `bg-white` inside its own error message, is not caught by its own rule.
      'no-restricted-syntax': ['error', ...SCRIPT_COLOUR_GUARDS],
    },
  },

);
