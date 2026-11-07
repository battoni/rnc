# api.rnc — context for AI agents

Laravel API. PHP 8.4, Laravel 13, Sanctum 4, Pest 4 (PHPUnit 12), Larastan 3, Pint 1.

Architecture, request flow, migrations and hygiene: `../.claude/rules/arcus-api-architecture.md`
(loads by `paths:` when a file here is read). Skills: `arcus-laravel-best-practices`,
`arcus-pest-testing`, `arcus-external-integrations`.

<!--
Written by hand on purpose. `boost.json` has `"guidelines": false` so `boost:update` does not
regenerate this file: the generated one (10 KB) pointed at the Laravel Boost MCP tools
(`search-docs`, `database-query`), which no session opened at the monorepo root has.
-->

## PHP

- Curly braces on every control structure. Constructor property promotion; no empty `__construct()`.
- Explicit return types and parameter types. Array shapes in PHPDoc. TitleCase enum cases.
- PHPDoc over inline comments; inline only for genuinely complex logic.

## Artisan

- Create files with `php artisan make:… --no-interaction` (`make:class` for a plain class). A new
  model gets its factory and seeder.
- `php artisan route:list --path=api`, `php artisan config:show <key>` to inspect.

## Tests and format

- Every change is tested. Run the smallest set: `php artisan test --compact --filter=<name>`.
- Never delete a test without approval. Factories (and their states) for test models.
- After editing PHP: `vendor/bin/pint --dirty --format agent` (the post-edit hook already runs Pint
  on the edited file).
