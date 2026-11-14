---
version: 1.28.0
origin: pendulum
based-on: 1.6.0
---
# RULES — Database (factories, seeders, migrations)

> For AI agents. Conventions beyond `arcus-api-architecture` §3–4.

## Seeders

- **`DatabaseSeeder` orchestrates order, and order matters**: `RoleSeeder` →
  `SuperAdminSeeder` → `ManagerSeeder`. Roles must exist before users that reference them by
  slug. Preserve this order when adding seeders.
- **Domain seeders are idempotent**: use `firstOrCreate` keyed on a stable column (a slug, a
  username) so re-seeding doesn't duplicate. It also never overwrites a row that already exists:
  a password changed in the environment reaches an existing account only through `make fresh`,
  not a re-seed. See `SuperAdminSeeder`. (This line used to say `updateOrCreate`, which no seeder
  here uses.)
- **Two password variables, both plaintext**: `SUPERADMIN_PASSWORD` for the superadmin,
  `SEEDER_PWD` for every other seeded account and the `UserFactory` default. The `hashed` cast
  on `User::password` hashes on save — seed the plaintext, not a hash. Set both on any server
  that seeds: a deploy that runs `migrate:fresh --seed` re-creates the accounts every time.
- **Seeders and factories read `config('seeding.*')`, never `env()`.** Under a cached config
  `env()` returns null and the seeder falls back to the guessable default in silence; larastan
  refuses the call (`larastan.noEnvCallsOutsideOfConfig`). `config/seeding.php` is the one place
  the two variables are read.
- **No throwaway accounts in `DatabaseSeeder`.** It seeded a `test@example.com` user with the
  password `password` on every environment, production included. Demo data runs on demand
  (below) — never in `DatabaseSeeder` nor in `seeders.local.php`, which `DatabaseSeeder` loads.
- **A project denies a key to the Manager in `manager-exclusions.local.php`, never in
  `ManagerSeeder`.** The seeder is synced; its `$excludedManagerPermissionKeys` holds only the core
  exclusions and spreads the LOCAL list (`PermissionGroupsHelper::getLocalManagerExclusions()`) into
  them. The local file can only take keys away.
- **A project denies a key to the seeded member in `user-exclusions.local.php`, never in
  `ManagerSeeder`.** Same pattern as the Manager list (`getLocalUserExclusions()`): the core member
  exclusions stay in the seeder, and a group a project adds locally is withheld locally.
- **A permission for a screen that is not open to people yet stays out of the seeded role lists.**
  Running a seeder to restore someone's access rewrites every role's rows, so a key sitting in a
  list "for later" is handed out the next time anyone reseeds. Declare the group; grant it when the
  screen is launched.
- **Demo data runs on demand, never in the seed chain**: `php artisan db:seed --class=XxxDemoSeeder`,
  outside `seeders.local.php`. It is idempotent by a natural key (a title, a username) so a screenshot
  session can run it twice, and a derived number is RECALCULATED from the rows (`available = total −
  Σ taken`), never decremented — a second run must not count twice.
- Seeders look roles up by **slug** (`where('slug', 'super-admin')`), matching the
  backend-owned slug convention.
- **A seeder that imports through a module's service keeps model events on, and the service stamps
  `firm_id` itself.** `DatabaseSeeder` runs under `WithoutModelEvents`, so `BelongsToFirm`'s `creating`
  event never fires inside the seed chain: `NfeSeeder` (rnc-2 F6b) goes through `NfeImporter`, which
  writes `firm_id` explicitly. A slow or file-writing seeder also refuses the `testing` environment
  (`NfeSeeder`, plan AM7): `SeedShapeTest` and others run the whole chain.
- **`seeders/Support/` holds builders shared by seeders and tests** (`NfeXmlBuilder`, the invented
  NF-e XML; `GeneratedNfeSource`): one shape for a seeded document and a tested one. Hostile fixtures
  (entity attacks, forged ZIPs) stay in `tests/Support/`.

- **A demo seeder runs once per seeded firm (rnc-4 F3b2, D18): `use Concerns\SeedsEachDemoFirm` and
  `forEachDemoFirm($currentFirm, fn (Firm $firm, int $ordinal) => …, FirmModules::X)`.** Franco
  (ordinal 0, rows unchanged), then Alvorada when it exists — only `DemoFirmSeeder` makes it, on a
  `local` machine — so the Pest seed carries Franco's demo alone. The module argument skips a firm
  with that module off; another firm varies names and random seeds by its ordinal; people come from
  the firm's roles (`teamOf`, `managerOf`), never a hard-coded Franco username; global keys
  (usernames, e-mails) get the firm's slug (`parceiro.<slug>.<n>`). `ChannelSeeder` and `BrandSeeder`
  stay Franco's. A new local-only seeder refuses every environment but `local` and is tested with
  `detectEnvironment(fn () => 'local')` and the NF-e disk faked (`tests/Feature/Seed/DemoFirmSeedTest`).

- **Modules (rnc-4 F3a, R16, D18, R23):** `FirmSeeder` gives Franco every key (`channel`, `brands`
  included), `DemoFirmSeeder` Alvorada every opt-in key but `brands`, `PlatformFirmSeeder` none.

## Factories

- `UserFactory` makes an **ACTIVE** user with no role (`role_id => null`); a test grants what
  it needs with `userWithPermissions()`. Never draw the status at random: INACTIVE is a
  deactivated account, signed out on its next request, and a random draw made half of every
  test's users unable to act.
- `UserFactory` puts the user in the **default firm** (`firms.default_slug`, `franco` under
  `phpunit.xml`), creating it when the test has none (rnc-4 F1a). A signed-in user with no firm
  now works in the platform firm or is refused `firms.required` (AM1), and user reads are scoped
  by firm (AM2), so a firm-less default broke ~100 tests; a test that means "no firm" passes
  `'firm_id' => null`.
- The factory password is the plaintext `SEEDER_PWD` (`password` in `phpunit.xml`), so a test
  knows the password it can log in with.
- Use `fake()->unique()` for `username`/`email` to satisfy the unique constraints.

- **`FirmFactory` defaults `modules` to every key** (rnc-4 F3a): the suite's implicit firm keeps every
  route it had before modules existed; a test about a module that is off sets `modules` itself.

## Migrations

- **A foreign key lives in the `create_*` of the table that holds it** (`arcus-api-architecture`
  §3). When the target table sorts later, rename the target's migration to an earlier timestamp
  — `roles` is `0000_00_00_000000` so `users.role_id` is declared in `create_users_table`.

- **A state with a moment is a timestamp, not a boolean.** `archived_at` says whether and since when,
  and the "since when" is what keeps an archived row counting in the weeks before it (see `tips`). The
  same for `pinned_at`: it also orders the pins in the order a person made them, where a flag would
  need a second column to keep in step. Delete is for the row that was a mistake.
- **A log row's reference to another module's row carries no foreign key.** A message log pointing at
  an order is an indexed `bigint`, with no Eloquent relation in the logging module: a log line
  must never block the delete of what it logged, and only the owning module declares the inverse.

## Do Not

- Do not reorder seeders so a user seeds before its role exists.
- Do not hardcode secrets, and do not read them with `env()` either — add the key to `config/seeding.php`
  and read `config('seeding.*')`. (This line used to say "use `env()` with a default", which
  contradicted the Seeders section above: under a cached config that `env()` is null and the
  default ships.)
- Do not pre-hash seeded passwords (the model cast does it).
