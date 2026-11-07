# AI Infrastructure Changelog

A running history of every significant decision, change, or addition to rnc's AI tooling.

---

## 2026-11-04 — The flow, read in a repo with no app or API

Found copying the tooling into battoni.dev (Astro sites only, no app.rnc or api.rnc). The commands described rnc's own Makefile and review commands as if every repo had them, so an agent there went looking for `make check-app`, `make sync-mirror`, `inject-rules-context.py` and `/battoni-review-*`, none of which exist.

- `/battoni-pre-commit`: the seven-step list is now what a repo with an app and an API chains; a repo without them runs its own `pre-commit` suite, and Step 2 says no convention review applies there (`code-reviewer` at `/battoni-verify` instead). The `check-cortex` line no longer says "rnc only": `specialize()` turned that into "<child> only" in every copy, which named the wrong repo.
- `/battoni-improve`: the injection-gap fix and the `.cursor` mirror step say what to do where the hook and `make sync-mirror` are absent.
- `/battoni-grill-me`, `midnight-oil`, `phase-review`: the review commands are called where the repo has them, with `code-reviewer` as the fallback.
- `PHASE-SPEC.md`: Backend/Frontend are named as the api.rnc/app.rnc anatomy; a repo with one or neither shapes the sections to its own units.
- `/battoni-explain-rnc`: the before-a-PR line points at `/battoni-pre-commit` and names the review commands only where the repo has an app or an API; the rule-editing paragraph says what to do without `make sync-mirror`.
- `/battoni-verify`: the rule files it names to the reviewer follow the same Type mapping as `code-reviewer`, so a site-only repo passes its own `CLAUDE.md`/`RULES.md` chain instead of only `shared-conventions.md` (`fable-reviewer` reads only the rules it is given).
- `code-reviewer` (the agent): its rule map listed only api.rnc and app.rnc; it now reads by the sub-project's Type, like `/battoni-start-session`, and says when no rule file covers a file — it is the review a repo without an app or API falls back to.
- Applying the Fable review of the above: `/battoni-improve` opens its PR against `development` only if the remote has it; `/battoni-grill-me` bumps versions only where units are versioned; `phase-review` says the metadata gate binds only units with a `RULES.md`; `/battoni-pre-commit` names `ci.yml` only where it exists; `PHASE-SPEC.md` no longer names api.rnc/app.rnc (downstream they became directories the child does not have).

The new text avoids the word "rnc", which the word map rewrites downstream. `/battoni-start-session` needed nothing: it already picks rules by each sub-project's Type.

## 2026-11-04 — Two names the sync broke in every child

Found syncing boanerges: two rnc names came out of `specialize()` wrong downstream.

- `cortex/hooks/test_claude_hooks.py`: `test_runs_eslint_on_the_edited_app.rnc_file_only` → `test_runs_eslint_on_the_edited_frontend_file_only`. The word map turns `app.rnc` into `app.<project>` inside the method name, a `SyntaxError` in every derived project (boanerges since its bootstrap, fixed there in its PR #27). `IDENT_COLLISION_RE` misses it: the token follows `_` and `.py` is not among the scanned suffixes.
- `cortex/sync/sync.py`: the manifest's `SKIP public.battoni.dev/` now lands as `SKIP public.<project>/`, as its comment always promised. No project word is in that name, so `specialize()` left it verbatim and the child's own public site fell into `MERGE *`; boanerges had added the line by hand and the sync overwrote it. New `specialize_file()` carries the one-file rewrite, used by the sync and by `--init`; only the manifest rule changes, so `public.battoni.dev/` elsewhere (the landing-page template) still names rnc's site. `test_sync.py` gains three checks (the first two red before the fix).
- `cortex/sync/sync.py`: `--audit` now catches the first one. The token-collision check scanned only MERGE files, only for a token at the START of an identifier, and skipped `.py`; the hook test was an OVERWRITE `.py` file with the token mid-name. It now also scans OVERWRITE files (outside the engine), `.py`, and a token after `_` or a digit.

## 2026-11-04 — The away mode: `/battoni-run` builds and verifies with nobody there

The owner's first feature on the new flow took longer than the old way of working, because every row waits for them to `/clear` and type the next command, and a run left alone simply stops. `midnight-oil` already ran phases unattended, but inside one session woken again and again, which re-reads every phase's files on every wakeup — the cost the flow was built to avoid.

- `.claude/feature-flow.md` gains "Two modes". **Attended** is the table as it was. **Away** is one sitting for the decisions (`/battoni-intent <idea> away` chains intent → spec → plan in one session, and the plan step grills every question a build would stop on), then `/battoni-run <id>`.
- `/battoni-run` (new, the 21st command) keeps the session a coordinator: each phase is built by a fresh `opus` subagent that follows `/battoni-start-session <id>` and returns ten lines. The coordinator reads only `state.md`, verifies through the existing reviewer and Chrome-driver agents, and stops at a draft PR. `midnight-oil` supplies the heartbeat, a 1800 s `ScheduleWakeup` fallback.
- **Decided while you were away**: a question takes its recommended option and is logged in `state.md` as `W#` (question, choice, why, how to reverse); the PR copies the list. Hard stops (irreversible or outward actions, an approved `R#`/`D#` that cannot hold, a blocked permission, a phase failing twice) block the phase, and no later phase starts on top of it.
- `state.md` keeps `## Away run` (settings, `Started:`/`Ended:`, one log line per phase, the failure count) and `## Decided while you were away` across every rewrite; a resume reads `Started:` and the `<id>: F<n>` commits on the branch, so it never redoes a phase. `fable-reviewer` can be chosen in the settings only in a session whose prompt has `#fable` (the owner's rule for Fable subagents), and `/battoni-verify` accepts it on the same condition; its layer 3 and, with Chrome QA off, layer 2 are listed as owed.
- Not offered: a one-prompt away run that writes the spec from recommended answers alone — a wrong guess there is hours of code built on it.
- The Preflight lists the permissions the run needs before the owner leaves: a merge was refused by the auto-mode classifier the same day, and a blocked call at 3 a.m. waits until morning.
- **boanerges review of the away mode** (fixed after #47 merged):
  - No two builders on one tree: the coordinator commits `F<n> started, attempt <a>` before it spawns, and a wakeup that finds a started phase with no `done`/`failed`/`blocked` line only re-arms (`blocked` counts too, or a crash after a block would re-arm all night). The coordinator writes `started`/`failed`, the builder `done`/`blocked`. The 1800 s fallback could fire mid-phase, see no commit, and spawn a second agent.
  - A phase commits as `<id>: F<n> …` (`/battoni-start-session` step 4, `feature-flow.md`), the subject a resume looks for; step 4 used to ask only for `<id>: …`.
  - Started under `/loop`: `/loop /battoni-run <id>`, in the run, the plan's closing line, "Two modes", the wakeup prompt and `midnight-oil`. `ScheduleWakeup` exists only in `/loop`'s dynamic mode, so a bare start had no heartbeat. **Still unproven live**: the first use should be a two-phase dummy feature.
  - Fable as above: offered only in a `#fable` session; it had been a new permission route through the settings.
  - Hard stops: the run's own push of the feature branch and its draft PR are planned steps, not decisions taken away.
  - ISO dates in the `## Away run` template (`<yyyy-mm-dd hh:mm>`).
  - Claude Code only: in Cursor, use the attended mode (no subagents, no `ScheduleWakeup`).
  - `app.rnc/src/modules/Showcase/views/Features/features.generated.json` regenerated: the catalogue gate was red.

## 2026-11-04 — Review fixes for the feature-flow back-port

boanerges reviewed the feature-flow back-port (#45, merge f422a6f); each finding below is fixed, with its reason.

- `/battoni-start-session` Step 2: an Astro row reads its own `<dir>/CLAUDE.md` first and loads `landing-page` only when the row's description says marketing or landing — a child's Astro site can be noindex and non-marketing, and that skill pushes SEO/AEO/GEO, `llms.txt` and conversion.
- `/battoni-start-session` Step 2: an Astro row that is neither marketing nor has its own `CLAUDE.md` falls back to "no rules; say so in Step 3", like the "Anything else" bullet — without it the row got no instruction at all (boanerges re-review).
- `/battoni-start-session` Step 1: "the root `CLAUDE.md` (in Cursor, `project-context.mdc`), already in context", because in Cursor the always-loaded file is the `.mdc`; and "plus `cortex` when it has no row of its own", because cortex is usually a row already.
- `/battoni-verify` layer 2: the login-as link now says where the username comes from (the project's QA notes, e.g. `qa.local.md`) and that it is opened on the API host — the path alone left the driver guessing both.
- `/battoni-spec` "High risk": the launched-project migration rule is stated inline (never edit a published `create_*`, change it with an `add_*_to_*` migration), because `arcus-api-architecture` §3's heading says the opposite and the exception sits in its last paragraph.
- `api.rnc/routes/api.php`: the login-as comment dates in ISO (`2026-11-04`), like every other date in the repo.
- `QaLoginAsTest`: a 13th case proves the route is NOT registered in `production` (loads `routes/api.php` into a fresh route collection and matches nothing); the "(7)" mutation only proved it is registered in `testing`. Red with the `environment('local', 'testing')` guard removed, green with it.
- `manifest.tsv`: `LOCAL brand/` — the brand chain the `battoni-new-brand` skill writes at the repo root (survey, `SYSTEM.md`, `design-system/`) is identity, and `MERGE *` kept it safe only while rnc ships no `brand/`. `test_sync.py` gains `brand/SYSTEM.md` among the LOCAL cases (red without the line).

**On the next sync:** a project that already registered `qa/login-as` in its `routes/api.local.php` (boanerges did) must delete that block and its `use` import. The core `routes/api.php` now registers the route, `api.local.php` loads later and would silently override it, and no test can catch a duplicate.

## 2026-11-04 — A local-only QA login link for the Chrome driver

Back-ported from boanerges (#25). Every Chrome QA round of boanerges-1 started at the login form,
where the owner's 1Password pop-up breaks the extension; one rerun met the login screen only on
the second try, and another ran on the previous round's session, so a round ran twice.
`/battoni-verify` now signs each round in by `GET /api/qa/login-as/{username}?to=/route`
(`api.rnc/app/Http/Controllers/QaLoginAsController.php`), which replaces the session and redirects
into the app.

- Registered only in `local` and `testing`, and the controller itself refuses anything but
  `APP_ENV=local` (404), so a wrong registration still signs no one in. Unknown or deactivated
  user → 404. `to` only takes a path inside the app (`//host`, `/\host`, full URLs → `/`).
- **In the api group, not web**: that group starts the session without `EncryptCookies`, so a
  session set by a web route is a cookie the API cannot read (boanerges tried web first: 302, then
  `auth/me` Unauthenticated).
- **In the core `routes/api.php`**, not `api.local.php` as in boanerges: everything back-ported is
  bootstrap, and the `.local.*` files stay the projects' extension point.
- `tests/Feature/QaLoginAsTest.php`, 12 cases. Each guard was re-proved by mutation here: env check
  (3 red), deactivated check (1), open `to` (5), route registered in `local` only (7). The one
  boanerges did not cover, dropping `session()->invalidate()`, survived all 11; the twelfth case
  (a fresh session, not the round before) fails without it.

## 2026-11-04 — `/battoni-start-session` travels by sync

The feature flow builds each phase with `/battoni-start-session <id>`, and the command was
`LOCAL` ("it lists THIS repo's sub-projects, so each project owns its copy"). Retired: LOCAL froze
every child's copy — lifeshifter, chapaja, largatudoevai and impressao still said "read all five
files" with nine app.rnc rules shipped, and none loaded `shared-conventions` — and the `<id>` mode
could never have reached them.

The sub-project list was the only project-specific part. The command now reads it from the
**Sub-projects** table of the root `CLAUDE.md` (LOCAL and always loaded) and loads rules by the
row's Type: the Laravel API → `arcus-api-architecture`, the Vue SPA → `celer-01…09`, a VitePress
`docs.*` → `docs-portal`, an Astro site → `landing-page`, anything else → none. The command and its
`.cursor` mirror are now OVERWRITE; `test_sync.py` asserts it (watched failing against the old
manifest: 4 failures). `/battoni-setup-project` no longer asks to delete a sub-project's line from
it. On the next sync each child's copy is replaced: what a child had added by hand there (impressao's
`docs.`/`public.` lines) is covered by the Type mapping.

## 2026-11-04 — The feature flow: intent → spec → plan → build → verify

Back-ported from boanerges (#20, refined by #21, #22, #23 and #26), so the flow is developed here
and every project receives it by sync. Adapted from Anthropic's AI-native SDLC playbook (each
stage commits a small file and ends in `/clear`), keeping the Battoni visual proposal where the
owner decides by seeing the screen. Reference: `.claude/feature-flow.md`.

- **Three tracks**: trivial (no ID), small (an ID, a five-line intent in the PR body), feature
  (an ID and `specs/<id>-<slug>/`: `intent.md`, `facts.md`, `spec.md`, `plan.md`, `state.md`,
  `proposal.html`).
- **Task IDs** `<TASK_PREFIX>-<n>` from `.cortex-sync.conf` (SKIP, each project keeps its own);
  missing → `PROJECT=`; the bootstrap, which has no conf, uses its folder name.
- **Commands** `/battoni-intent`, `/battoni-spec`, `/battoni-plan`, `/battoni-verify` (new), and
  `/battoni-start-session <id>` builds one phase.
- **The owner picks, never types** (#23): every question through `AskUserQuestion`, recommended
  option first.
- **The proposal is local and decides nothing** (#22, #26): built from `spec.md` by a Sonnet author,
  early only to see an open decision, final as settled cards + working demo, frozen; published as
  an Artifact only when the owner asks. The page's choice cards, versions and clips were retired
  (58% of boanerges-1's spec stage, US$ 3.29 of 5.68, helped nobody decide).
- **Verify** (#21): one clean-context review on the whole branch by the lean `fable-reviewer`
  (Fable needs the owner's permission; otherwise `code-reviewer` on Sonnet), then tests → Chrome
  driver → a person, and a PR mapping each R# to the test that proves it. `code-reviewer` reads a
  `spec.md` when given one and reports R#/A# gaps.
- `/battoni-plan` budgets a phase under ~150k context (boanerges-1's F4 reached 206k) and asks a
  Fable review only of a high-risk spec.
- `/battoni-grill-me` and `/battoni-agent-handoff` point feature work at the flow (`state.md` is
  the handoff); `midnight-oil` runs build rows from `state.md`.
- Manifest: `specs/` is `LOCAL`. `specs/README.md` is rnc's own.

Generalised on the way in: example IDs are `rnc-<n>` (the transform makes them each
project's); boanerges' named tester and elders became "a tester, the client"; its private `data/`
folder became "never a real record"; the login link of its `qa.local.md` became the QA login-as
route that ships with api.rnc; its production-migration rule became a pointer to
`arcus-api-architecture` §3 and the project's own `CLAUDE.md`.

## 2026-11-04 — `/battoni-publish-pr` opens against `main` when there is no `development`

Back-ported from boanerges (#20), which has only `main` and stopped at this step on every PR.
The base is now `development` when it exists on the remote, otherwise `main`, and the report
names the base used. What did not change: when `development` exists and the push or the `gh`
call fails, the command stops and reports — it never falls back to `main` to get past an error.
`/battoni-agent-handoff` writes the base the same way.

## 2026-11-04 — Token economy and the delegation policy

Back-ported from boanerges (#20), where a week of tokens was measured (2026-11-02): ~99% of the
cost is context being re-read, since whatever enters a session is paid again on every later turn.
After the change, boanerges' first feature on the new flow cost ~3× less per call and ~38% less
overall.

- `shared-conventions` gains **Token Economy**: read the slice, not the file; delegate by the
  policy and always name the subagent's model (one spawned without it inherits Opus); Chrome
  through `battoni-chrome-driver`; truncated rtk output is a partial result; `/clear` and
  `/compact` hygiene.
- `.claude/delegation-policy.md` (new, OVERWRITE): who does what (Haiku reader, Sonnet `Explore`,
  light author, Chrome driver and reviewers, Opus for judgment), the SUMMARY vs RAW return
  contract, find-then-verify, when NOT to delegate, the Cursor fallback.
- `.claude/agents/battoni-reader.md` (new): the Haiku read-only fetcher the policy names.
- `code-reviewer` and `security-auditor` pin `model: sonnet` (an alias, not a dated id);
  `phase-review`, `arcus-laravel-best-practices` and `OVERNIGHT-BUILD.md` name the model of the
  agents they spawn.

## 2026-11-04 — rules and skills load by `paths:`; Boost's skill copies retired

Back-ported from boanerges (#20). The rules declared Cursor's `globs:`/`alwaysApply:` in their
Claude frontmatter, which Claude Code does not read: a rule loads by `paths:`, so the app.rnc and
api.rnc rules were either loaded by hand (`/battoni-start-session`) or not at all. Every
`.claude/rules/*` now names its `paths:`, `shared-conventions` has none (always loaded), and the
api.rnc/app.rnc skills declare the folder they serve. The `.cursor` mirrors keep `globs:` — only
bodies are mirrored.

`api.rnc/.claude/skills/` was Laravel Boost's generated copy of the root `arcus-*` skills — two
versions of one skill, the copy loading only in a session opened inside `api.rnc/`. Deleted and
`RETIRED` in the manifest; `boost.json` stops targeting `claude_code` and sets
`"guidelines": false`, so `boost:update` no longer regenerates `api.rnc/CLAUDE.md` (10 KB pointing
at the Boost MCP tools, which no session opened at the monorepo root has). `api.rnc/CLAUDE.md` and
`app.rnc/CLAUDE.md` are rewritten by hand to what is easy to get wrong and point at the rules for
the rest; `arcus-laravel-best-practices` verifies syntax against `vendor/laravel/framework`
instead of `search-docs`.

## 2026-09-23 — `--init` names a file the way a sync would

`sync.py --init` renamed two folders and left every other path alone, while rewriting every
file's CONTENT into the new project's vocabulary. The two halves disagreed, and the disagreement
was silent: `api.rnc/config/rnc.php` kept its name in a repo where the code now asked for
`config('zion.…')`, so every lookup answered null with nothing anywhere to say why — it surfaced
as one failing Pest test on zion's first run, hours after the setup. `.claude/commands/battoni-explain-rnc.md`
and its `.cursor` mirror had the same problem, as did `docs.rnc/`. And because a sync DOES
name-map paths (`specialize()` runs on the path in `build_merge_plan` and in the OVERWRITE
writer), the first sync would have written `config/zion.php` beside the file init never renamed.

`--init` now maps every path through the same `specialize()` the sync uses. A directory moves as
a directory, so its untracked and ignored content (`node_modules/`, `vendor/`, `.env`) travels
with it; everything else moves file by file, after the folders, and `git mv` keeps the history.
Two paths that would collide on one name stop the run with both names printed, rather than one
quietly overwriting the other.

`celer-`/`arcus-` rule filenames are still protected — that guard lives inside `specialize()`,
which is exactly why the fix reuses it instead of a second rewrite table.

The same disagreement lived in the OVERWRITE writer, and fixing init alone would have made it
worse. That writer took the raw path — "the AI layer carries no project token in its names" — and
`battoni-explain-rnc.md` plus its `.cursor` mirror are exactly that counter-example: every
project has them named after rnc while their text speaks of the project. With init naming
them correctly, the next sync would have written the rnc-named copy back BESIDE the right
one: two commands, the stale one holding the old text. The writer now name-maps its path too, and
removes the copy under the previous name — an OVERWRITE file is rnc's to own, so a second
answer to the same question is not left behind. `test_engine.sh` gained four checks for it, all
watched failing first. The four children will take the rename on their next sync.

`test_init.sh` gained eight checks, and all eight were watched failing against the previous
engine first: the config file's name, that the name matches what the code asks for, the command
and its mirror, the docs folder, that no tracked path outside `cortex/sync/` still carries the
source word, that the dry-run lists a file rename, and that git recorded a rename rather than a
delete plus an add.

## 2026-09-22 — The transform rewrites words, not letters

### What changed

- **`specialize()` rewrites a project word only where it stands as a word.** `str.replace` also
  rewrote it inside other words: `accelerometer` arrived as `acapp.Xometer` in every
  Permissions-Policy header, Laravel Boost's description in `composer.lock` "acapp.Xates", and the
  landing's testimonial author "Marcus" became "Mapi.X". An uppercase letter, `_` or digit after
  the word still counts (`app.rncPreset`), so the identifier-collision gate keeps seeing those.
- **The dev hosts map once.** `app.rnc.test` / `api.rnc.test` are rewritten as a
  unit to `app.X.test` / `api.X.test`; word by word they arrived as `app.X.X.test` in
  `.env.example`, the CORS default and `config/rnc.php`.
- **A host written inside a regex maps too.** The CORS pattern spells it with escaped dots
  (`app\.rnc\.test`), which the plain mapping did not match: it still arrived as
  `app.X\.X\.test` in `api.rnc/.env.example`. Found on largatudoevai's second sync (22/09).
- **`origin: rnc` is guarded only in the frontmatter that opens the file.** Guarded
  everywhere, the review commands arrived downstream saying "(X-origin units) … frontmatter has
  `origin: rnc`", contradicting themselves, and the generate-rules templates stamped
  `origin: rnc` on units the child itself was writing. Found in lifeshifter's T0 (PR #11).
- **`contrast.spec` test 1 asks the stylesheets who declares `--color-primary-contrast`**
  instead of requiring it to equal `--color-ink-900` — battoni-dev's answer, which failed every
  white-label brand (chapaja's Terra, lifeshifter's terracotta) while their themes declared the
  token exactly as the test demands. `app.rnc/e2e` 1.8.1.

### Why

Three defects of the upstream that every phase-2 sync re-delivered to every child, fixed here
before the post-QA changes (PR #39, `a83cf4f`) are extended to them. Measured with lifeshifter's
mapping over every tracked file: 35 files transform differently, and every difference is one of
the four above.

---

## 2026-09-17 — The hooks fire, the mirrors cover the skills, the handbook can be checked

### What changed

- **Hooks** — `post-edit.sh` and `pre-bash-guard.sh` read `$CLAUDE_TOOL_INPUT`, which Claude Code
  never sets, so neither ever ran. Both now read the hook JSON from stdin, match the path relative
  to the repo root, and fail open. `post-edit.sh` formats only the edited file (it called
  `npm run lint`, which is `eslint .`). The guard now also catches `git push origin main -f`, and
  no longer matches `rm -rf /tmp/x` as `rm -rf /`. Tests: `cortex/hooks/test_claude_hooks.py`.
- **Mirrors** — `cursor_mirror.py --check` covers the skills that have a `.cursor/rules` counterpart.
  `celer-testing`, `cortex-backport` and `cortex-sync` had drifted and were regenerated. Test:
  `cortex/sync/test_cursor_mirror.py`. `celer-09` gained the frontmatter the other rules carry, and
  its `.mdc` no longer calls it a command.
- **Commands / agent** — `code-reviewer`, `/battoni-start-session`, `/battoni-review-vue-conventions`
  and `/battoni-generate-component-rules` read `celer-01` … `celer-09` plus `shared-conventions`,
  instead of stopping at `celer-05`.
- **Sync engine** — `attribution.css` is allowlisted in `--brand-audit`, because naming the brand is
  its job. `codelumen/scripts/generate-rules.mjs --check` fails on a stale, missing or orphaned page.
- **Docs** — `CLAUDE.md` / `project-context.mdc` list the real tree: all 12 skills, the 16 commands
  and the three hooks. `cortex/README.md` and the codelumen pages use the `battoni-*` command names.

## 2026-09-16 — The final review of the children: the wire, the seeds, the rules

### What changed

- **Rules** — `arcus-api-architecture`: `DTO::from($request)` maps the validated input because
  `config/data.php` enables `FormRequestNormalizer` (chapaja-116); ids are integers on the wire
  (lifeshifter-269); a route without auth answers through a `Public*` allow-list resource
  (largatudoevai-039); a `<select>` is fed by `/options`, never by a paginated index
  (chapaja-027/092/253); a status with rules is a transition graph on its enum (chapaja-105); what
  was agreed is copied onto the row (chapaja-038); a child row carries no second owner column and a
  module reads another's tables but never writes them (lifeshifter-097/076). §5 no longer tells a
  seeder to read `env()`, which `database/RULES.md` and larastan refuse.
- **Skills** — `arcus-external-integrations`: the audit row masks a secret that signs a person in;
  card data only through the provider's hosted field (PCI scope); a provider event is matched by the
  id the provider returned, never by what the person typed (largatudoevai-028/037/025).
- **Sync engine** — `Auth/Helpers/manager-exclusions.local.php` is a new LOCAL extension point (the
  `*.local.php` pattern already classifies and scaffolds it; `test_sync.py` now names it and the
  RUNBOOK table lists it), so a project denies a key to the seeded Manager without editing
  `ManagerSeeder` (largatudoevai-016).

## 2026-09-16 — Readable text has a tier, and the synced layer stops leaning on codelumen

### What changed

- **Rules** — `celer-06`: `subtle` never carries a sentence; hints, timestamps, subtitles and
  placeholders are `text-muted` (4.5:1), `text-subtle` is icons and marks (3:1). The old "hint text is
  `text-muted` to `text-subtle`" is retired with its reason (from lifeshifter). `celer-01` checklist and
  quick reference aligned.
- **Rules / commands / skills no longer depend on `codelumen/`** (from largatudoevai). `celer-06` stated
  it as its "canonical source" and `battoni-review-design-conventions` sent the reviewer there; both
  are self-contained now and mention codelumen only as "if the project keeps it". The `landing-page`
  skill says where the recipe lives when it is present and what to do when neither it nor
  `public.battoni.dev/` is in the repo. `battoni-setup-project` removes a dropped sub-project's row from
  `CLAUDE.md` and `project-context.mdc`, and states the rule for the synced layer.
- **`.cursor/rules/project-context.mdc`** mirrors `CLAUDE.md` again. It had drifted: it named a
  `cortex/start-session.md` and a `/sync-cortex` command that do not exist, and "all 5 app.rnc rules"
  where there are nine.
- **codelumen** — `design-system.md` carries the same tier rule; the generated pages for the rules and
  commands touched here were regenerated. The other generated pages that had drifted were left alone.

### Why

A synced rule that points at a path the project deleted is a rule that points at nothing, and it
fails silently: the agent reads the pointer, finds no file, and applies its own guess.

## 2026-09-16 — Final review of the children: dates, countdowns, filtered lists, polling

### What changed

- **Rules** — `celer-03` "Dates and Time": a calendar day or local date-time never goes through
  `toISOString()` (`toLocalDateString` / `toLocalDateTimeString` in `@Helpers`), and a countdown is
  measured by the wall clock (`useCountdown`) (lifeshifter-265, chapaja-272, largatudoevai-088);
  `celer-07` a create on a filtered list reloads page 1 instead of prepending (chapaja-224), and a
  poller has a time ceiling and a consecutive-failure ceiling, pauses while hidden and resumes when
  seen again (chapaja-276). Both mirrored to `.cursor/rules`.
- **Skills** — `celer-advanced-patterns`: anything timed reads the wall clock, and how a spec models
  a suspended phone.

---

## 2026-09-14 — The queue round: the other children, by capability

### What changed

- **Rules** — `celer-04` "Which prop reaches the real `<input>`" (PrimeVue `id` vs `inputId`,
  `Password` `inputProps`), guarded by `src/test/primevueInputProps.spec.ts`; `celer-06` "Narrow
  screens: shrink, never hide the label", "The label on a filled button", the card as relief rather
  than a border, and the radius scale read from the theme; `celer-07` a missing permission hides the
  part that needs it and never becomes a grant; `arcus-api-architecture` normalise identifiers in
  `prepareForValidation()`; `shared-conventions` a new test counts only after it has failed, and a
  batch commit carries each suite's count.
- **Skills** — `arcus-pest-testing`: `describe()` with the incident in a docblock, and prefixed global
  helpers; `celer-testing`: fake only `Date`/`setInterval` in an integration spec, and domain MSW
  fixtures whose handlers refuse what the API refuses.
- **`cortex/qa/CHECKLIST-QA.template.md`** — the structure of lifeshifter's manual QA checklist
  without its content.
- **`cortex/playbooks/OVERNIGHT-KICKOFF.md`** — the kit that starts an overnight build, with every
  product, person and secret turned into a slot; the README now indexes the playbooks.
- **Process** — `shared-conventions` asks a commit body for the symptom, the cause, the number measured
  and the rejected alternatives, and a review commit lists what it did not apply; the RULES template
  asks an intentional decision for the alternative it rejected; a parked phase leaves
  `cortex/plans/phases/<id>-handoff.md`; a plan's conventions cite the file that does it;
  `cortex-sync` says to extend a synced file through its LOCAL half; `OVERNIGHT-BUILD` §7 gained the
  layout matrix and the rule for a builder's half-finished work; `docs-portal` asks for concept pages
  beside the extracted reference.
- **Sync engine** — `*.local.vue` is LOCAL and a scaffolded stub, for `TheLayout`'s new `TheLocalOverlays.local.vue` slot: the LOCAL extension points held data only (routes, nav items), so a project that needed its own store-opened overlay had to edit a synced file.
- **Rules** — `celer-07` a load that failed is not an empty list; `arcus-api-architecture` §3 a fold is
  verified by diffing the schema; `shared-conventions` a rule is retired with its reason, and a known
  gap lives under `## Known Gaps` in the unit's `RULES.md` (the RULES template gained the section).
- **Docs portal (template and `docs.rnc`)** — the endpoint extractor resolves `->middleware($var)`
  and reports every key of `permission:a,b`: routes guarded through a variable were documented as open
  to anyone. `planned-seeds.mjs` documents what a phase specified and has not built;
  `EntityDiagram`, `ReachabilityMap` and a client-only `MermaidRender` join the theme. The guide
  warns about a root `.vercelignore`.
- **Rules** — `celer-07` the overlay `<aside>` is `contents`, and one poller per screen owned by the
  view; `celer-06` nothing renders below 12px; `celer-05` a module reaches another's screen by an
  exported route name; `celer-09` a mark's SVG is never imported with `?url`;
  `arcus-api-architecture` rows a person owns answer 404 and an `exists` asks whose, a resource is
  closed by default, a body-dependent permission is checked in the controller, a partial update
  validates against what is stored, and a create race reads the winner.
- **Skills** — `celer-advanced-patterns` optimistic writes with a snapshot, and one loader per shared
  list; `arcus-laravel-best-practices` caches the resolved array under a versioned, normalized key, a
  queued mailable carries its locale, a day column is not a datetime on SQLite, no `const` in a routes
  file, `Str::ascii` over `iconv`. The RULES template gained `## Test IDs`; the landing recipe the LCP
  preload, content-visibility, immutable assets and the share card.

## 2026-09-14 — The chapaja back-port round: what a child learned, returned to the bootstrap

### What changed

- **`cortex/playbooks/OVERNIGHT-BUILD.md`** — the playbook for an unattended multi-hour build with
  parallel agents (contract, plan file, foundation before fan-out, conflict rules for one working
  tree, browser-automation traps, verification, the report). Paired with `midnight-oil`, which owns
  crash survival.
- **`cortex/playbooks/planning/`** — the shapes of the four planning documents: product memory with a
  Decisions Log and `⚠️ OPEN` items, estimate in effort hours with the loop's honest limits, roadmap
  with a dated "what the bootstrap already gives", and a fixed phase-spec anatomy (out of scope,
  drift check, carry-forward). `cortex/plans/phases/README.md` points at the spec shape.
- **`/battoni-grill-me`** — the plan lists every decision the grill closed as `D<n>` entries for the
  product memory, so a later spec cites a decision instead of re-arguing it.
- **Sync engine** — a `RETIRED` manifest mode (the pre-`battoni-` command generation), reported on every
  sync and deleted with `--prune-retired`; the session command's LOCAL entry now names the live file;
  the loss report no longer lists unit metadata (`version`/`origin`/`based-on`/"Last updated"); a
  `CLAUDE.md` drift report. `--audit` refuses a RETIRED entry that names a tracked file.
- **`SCHEMA.md` + `cortex-sync`** — the **Accepted divergence** convention: a project that diverges on
  purpose writes what the sync must not revert in the unit's `RULES.md` body.
- **Rules** — `celer-07` (managed lists are server-paginated; confirm-popup actions go in `#actions`;
  a 422 is a warning, not an error) and `celer-05` (resource and payload interfaces never cross).
- **Docs portal template** — the extractors read per-view `routes.ts`, `TheNavbar.vue` and every
  `.local.` twin, match nav items to localized route stems, and apply `Schema::table` alters from
  `up()` only.

### Why

chapaja was the first child triaged item by item (161 back, 207 product-local). Its AI-layer changes
were real and had no entry here — the child's own `cortex/CHANGELOG.md` was byte for byte rnc's.

Two defects of the parent surfaced on the way. The portal documented **2** of rnc's screens and
**0** navigation items, because its extractor read a `NavTemplate.vue` and a `views/routes.ts` this
bootstrap does not have — it now documents 39 and 10. And the manifest protected the pre-rename
`start-session.md` as LOCAL, so in every child the command a person runs was overwritten with
rnc's sub-project roster while the project's adaptation sat in a file nobody invokes.

### Files

- `cortex/playbooks/OVERNIGHT-BUILD.md`, `cortex/playbooks/planning/*` (new)
- `cortex/sync/sync.py`, `cortex/sync/test_sync.py`, `cortex/sync/manifest.tsv`, `cortex/sync/RUNBOOK.md`, `cortex/sync/SCHEMA.md`
- `.claude/commands/battoni-grill-me.md`, `.claude/skills/cortex-sync/SKILL.md`, `.claude/rules/celer-05-module-conventions.md`, `.claude/rules/celer-07-view-patterns.md` (+ `.cursor` mirrors)
- `cortex/docs-portal/templates/scripts/extract-{views,endpoints,schema}.mjs`, `extract.mjs`

---
## 2026-09-10 — `based-on == version` moves to where make runs it

### What changed

Six `origin: rnc` units realigned (`AEmptyState`, `ALogo`, `ONavbarDesktop`,
`ONavbarDrawer`, `TheCenteredLayout`, `TheLayout`), and the invariant added to
`sync.py --audit` as `BASED-ON DRIFT`, with the comparison extracted pure
(`based_on_drift_in_text`) and unit-tested.

### Why

This is the second time. On 2026-06-14 thirteen units were realigned and the assertion was
added — to `/reviewVueConventions`, a review command a human has to remember to run. By
2026-09 six had drifted again, so the gap closed the process and not the hole.

Three of the six were invisible to the triage, which is why nobody saw them: `ALogo` is LOCAL
in the manifest and never compared, and the two navbar organisms carried a `based-on` from the
project they were back-ported FROM (1.8.1 and 1.6.1, both HIGHER than their own version) —
drift in the opposite direction from the one anybody was looking for.

The cost was not cosmetic: `make sync-test-engine` had been failing on `fresh sync: 0 diverged`
since before PR #30, because the triage read the gap as divergence and reported a freshly
synced target as changed. The engine proof passes again.

### Files

- 6 `app.rnc/**/RULES.md` — `based-on` realigned to `version`
- `cortex/sync/sync.py` — `based_on_drift_in_text` + `find_based_on_drift`, wired into `--audit`
- `cortex/sync/test_sync.py` — 7 checks, including both drift directions

---

## 2026-06-15 — New `docs-portal` skill (dual-audience documentation)

### What changed

Added the `docs-portal` skill (`.claude/skills/docs-portal/SKILL.md`) plus its depth and a
copyable scaffold under `cortex/docs-portal/` (`GUIDE.md` + `templates/`). It captures the
documentation pattern we built for `docs.trainingbeta` and `docs.impressao`: a VitePress portal
with a **User/Developer depth switcher** (`::: user` / `::: dev` containers + a `mode-*` class on
`<html>`, anti-FOUC, pure-CSS show/hide so content stays in the DOM), a unified sidebar, Mermaid
diagrams, codebase **extractors** (routes/migrations/views → `.vitepress/data/*.json` rendered by
Vue data components, with `--check` freshness CI), and a fail-closed **Vercel Edge staff gate**.
Templates use `{{PLACEHOLDERS}}`; the gate logic ships with passing `node --test` coverage.

### Why

We did this twice by hand and liked the result. It's a high-value, repeatable deliverable —
especially as a client handoff artifact (owner reads User mode to run the system; the next dev
reads Developer mode to continue it). A skill makes the pattern reusable instead of rebuilt.

## 2026-06-14 — `reviewVueConventions` now gates `based-on == version`

### What changed

Added a third assertion to the A.6 changelog/version gate (`/reviewVueConventions`): for any
`origin: rnc` unit, `based-on:` must equal `version:`. New flag `[A.6 — based-on drift]`.
Regenerated the Cursor mirror.

### Why

The gate already checked `version == CHANGELOG` but not `based-on == version` — which is exactly
how the theme pass let 13 units drift and poison the sync triage (see the entry below). Closing
the process gap so the drift can't recur.

### Files affected

- `.claude/commands/reviewVueConventions.md` — third A.6 assertion + flag
- `.cursor/rules/reviewVueConventions.mdc` — regenerated mirror

---

## 2026-06-14 — Fix `--init` self-mangling + realign `based-on` drift

### What changed

Two engine bugs, surfaced while preparing the first downstream sync (vigil):

1. **The engine rewrote itself into the project's vocabulary** — on *both* transform paths.
   `init_in_place()` and the regular sync's **OVERWRITE writer** both `specialize()`d *every*
   file including `cortex/sync/*.py`, so the rewrite table's `("app.rnc", app)` entries became
   `("app.<project>", app)` and doc source-names flipped (RUNBOOK's "run from rnc"). In init
   it only bit on the *next* engine load (Python holds the pristine function in memory for the
   pass — a self-modifying-code footgun); via OVERWRITE it shipped a broken engine downstream,
   where `make sync-test` actually runs it. Fix: a new `is_engine_internal()` guard makes **both**
   paths write everything under `cortex/sync/` **verbatim**, **except `manifest.tsv`** (which
   legitimately needs the renamed `app.X/api.X` path tokens for the downstream `--audit`).
   Regression checks in `test_init.sh` and `test_engine.sh` (engine byte-identical to source;
   manifest still transformed; RUNBOOK keeps the rnc name).

2. **`based-on` drift on 13 units.** The battoni-dev theme pass bumped `version` but left
   `based-on` behind, violating the rnc invariant `based-on == version` (`SCHEMA.md`). The
   sync triage then read those units as falsely **diverged** on a fresh sync (caught by
   `make sync-test-engine`, "fresh sync: 0 diverged"). Realigned all 13. Added the invariant as
   an explicit rule in the `cortex-sync` skill (+ Cursor mirror) so a future bump can't recreate it.

### Why

The engine is the one artifact that must stay pristine across the rnc→project boundary — a
corrupted copy throws spurious merge conflicts on later syncs. And a stale `based-on` quietly
poisons triage classification, the very signal a maintainer trusts to decide how a unit merges.

### Files affected

- `cortex/sync/sync.py` — `is_engine_internal()` self-exclusion in `init_in_place()`
- `cortex/sync/test_init.sh` — engine-pristine regression checks
- 13 `app.rnc/**/RULES.md` — `based-on` realigned to `version`
- `.claude/skills/cortex-sync/SKILL.md` + `.cursor/rules/cortex-sync.mdc` — `based-on==version` rule

---

## 2026-06-14 — Sync the `celer-testing` Cursor mirror to the tier model

### What changed

The Claude `celer-testing` skill was refreshed to the three-tier model during the testing
build (VTU `mountWithPlugins` for unit, Testing Library `renderWithPlugins` + MSW for the
`.integration.spec.ts` tier, Playwright for e2e, plus the JSON-reporter gotcha), but its Cursor
mirror (`.cursor/rules/celer-testing.mdc`) had drifted to the older, shorter version. Rebuilt
the mirror body byte-identical to the canonical `SKILL.md` (Cursor frontmatter only differs).
Completes `cortex/sync/SKILL-PLAN.md` §2.

### Why

Cursor agents were being handed pre-tier-model testing guidance — the two assistants must teach
the same discipline. `cursor_mirror.py` only covers rules and commands, so skill mirrors are
synced by hand.

### Files affected

- `.cursor/rules/celer-testing.mdc` — body resynced to `.claude/skills/celer-testing/SKILL.md`
- `cortex/sync/SKILL-PLAN.md` — §2 marked done

---

## 2026-06-14 — `cortex-sync` skill: propagation discipline as an auto-loading skill

### What changed

Authored the `cortex-sync` skill (planned in `cortex/sync/SKILL-PLAN.md` §1). It encodes the
metadata + propagation rules that previously lived only as prose in `cortex/sync/*.md`: triad
discipline (source + `RULES.md` + `CHANGELOG.md`), version-on-change (CHANGELOG canonical,
frontmatter mirrors it), tests-are-part-of-the-unit, sync MODE awareness
(OVERWRITE/MERGE/LOCAL/SKIP), naming/transform protection, parallel-session staging (never
`git add -A`), and one-way rnc→projects flow. Trigger-loads (not `alwaysApply`) on
`RULES.md` / `CHANGELOG.md` / `cortex/sync/**` / `.cortex-*`. Claude + Cursor mirrors authored
by hand (the mirror script covers rules and commands, not skills) with byte-identical bodies.

### Why

Written discipline only binds when an agent is pointed at it. A skill auto-loads on the right
triggers, turning the docs into default behavior — and because it is committed markdown in the
AI (OVERWRITE) layer, it propagates to every rnc-derived project via the sync system itself.

### Files affected

- `.claude/skills/cortex-sync/SKILL.md` — canonical skill
- `.cursor/rules/cortex-sync.mdc` — Cursor mirror (identical body, trigger-load globs)
- `cortex/sync/SKILL-PLAN.md` — §1 marked built; trigger-load scope decision recorded

---

## 2026-06-04 — Codelumen becomes the internal handbook (generated from rules & commands)

### What changed

Restructured Codelumen from a celer-only conventions site into the **rnc internal handbook** covering every sub-project, the workflow, and the AI tooling. A zero-dependency generator (`codelumen/scripts/generate-rules.mjs`) renders one page per `.claude/rules/*` rule **and** per `.claude/commands/*` command, plus the sidebar data — so the handbook can never drift from the canonical sources. Added authored overview pages (monorepo map, AI tooling, per-project, access control) and retired the stale prose pages. Command pages live under the Cortex sidebar group.

### Why

The handbook had drifted from the real rules (the "Liquen leak"). Generating from `.claude/` makes the published docs exactly what the tooling enforces, with no second copy to maintain.

### Files affected

- `codelumen/scripts/generate-rules.mjs` — generator (rules + commands), escapes bare `<…>` so source placeholders don't break the Vue build
- `codelumen/rules/**`, `codelumen/commands/**` — generated, committed
- `codelumen/.vitepress/config.mts` — sidebar wiring + mermaid rendering
- `codelumen/{monorepo,ai-tooling,access-control}.md`, `codelumen/projects/*.md` — authored
- retired `formatting-reference.*`, `conventions.md`, `implementation.md`, `concepts.md`, `architecture.md`, `html-templates.md`

---

## 2026-06-04 — `.claude`/`.cursor` mirror sync gate

### What changed

`.claude` is now the **single canonical source** for every rule and command; `.cursor/rules/*.mdc` are generated mirrors with identical bodies. `cortex/sync/cursor_mirror.py` regenerates them (`make sync-mirror`) or verifies them (`make sync-mirror-check`). The check runs inside `make pre-commit` and as a CI job, so a drifted mirror can never reach `main`.

### Why

The two formats had silently diverged across nearly every rule. A canonical source plus an enforced gate keeps Claude Code and Cursor users governed by exactly the same rule.

### Files affected

- `cortex/sync/cursor_mirror.py` — new
- `Makefile` — `sync-mirror`, `sync-mirror-check`; `pre-commit` runs the check first
- `.github/workflows/ci.yml` — `mirror-sync` job
- `.cursor/rules/*` — re-synced from `.claude`

---

## 2026-06-04 — GitHub OAuth access gate for hosted apps

### What changed

Added `codelumen/middleware.ts` (Vercel Edge Middleware) gating the deployed handbook behind **GitHub OAuth** with a username allowlist (`ALLOWED_GITHUB_USERS`). The middleware runs only on Vercel, so local/static serving stays ungated. Documented on the handbook's Access Control page; the same pattern protects any app we host for a client.

### Why

Codelumen — and future client apps — must be reachable online but visible only to specific people. Vercel Hobby's built-in protections don't fit (paid, or Vercel-team-only), so access is enforced at the edge.

### Files affected

- `codelumen/middleware.ts` — new (`jose` + `@vercel/edge`)
- `codelumen/.env.example`, `codelumen/vercel.json` — env template + `/_auth` rewrite exclusion
- `codelumen/access-control.md` — docs

---

## 2026-06-04 — `make handoff` client-delivery eject

### What changed

`cortex/sync/handoff.py` (`make handoff`) produces a clean copy of the repo for client delivery: it exports the committed tree, **strips all rnc AI tooling** (`.claude`, `.cursor`, `cortex`, `codelumen`, `plans`, `CLAUDE.md`, tooling Makefile/CI targets), **keeps the component `RULES.md`**, and re-inits git so the tooling isn't recoverable from history. Supports `PROJECTS=`, `OUT=`, `VERIFY=`.

### Why

When handing an app to a client we keep the reusable framework and AI tooling as our IP. The eject enforces that split technically; the contract is the legal backstop.

### Files affected

- `cortex/sync/handoff.py` — new
- `Makefile` — `handoff` target
- `cortex/README.md` — Client handoff section

---

## 2026-05-26 — Git workflow commands (`/commit`, `/publish-pr`, `/create-pr`)

### What changed

Added two new commands and expanded a third to create a full commit-to-PR workflow:

| Command | What it does |
| --- | --- |
| `/commit` | Reads all changed files, classifies them by layer and concern, groups them into bottom-up conventional commits, presents the plan for approval, then executes sequentially |
| `/publish-pr` | Pushes the current branch, runs `/create-pr` to draft the PR body, then creates a draft GitHub PR via `gh pr create` |
| `/create-pr` | Expanded — now generates title + structured body (summary bullets + test plan checklist) from the full branch diff |

Both `/commit` and `/publish-pr` are available in Claude Code (`.claude/commands/`) and Cursor (`.cursor/rules/`).

**Supporting change:** `.gitignore` updated to exclude `plans/prs/*.md` — ephemeral plan files generated during PR drafting are not committed.

### Why

The previous workflow had `/create-pr` but no standardised way to stage/commit or to push and open the PR. Agents were inconsistent about grouping commits and writing conventional commit messages. The new commands enforce bottom-up layer ordering, one-concern-per-commit discipline, and a repeatable PR format without requiring manual steps.

### Files affected

- `.claude/commands/commit.md` — new
- `.claude/commands/publish-pr.md` — new
- `.claude/commands/create-pr.md` — expanded
- `.cursor/rules/commit.mdc` — new
- `.cursor/rules/publish-pr.mdc` — new
- `.cursor/rules/create-pr.mdc` — expanded
- `.gitignore` — exclude `plans/prs/*.md`

---

## 2026-05-26 — RULES.md auto-injection hooks (app.rnc)

### What changed

Implemented automatic injection of folder-specific `RULES.md` chains before every **Edit** or **Write** on app.rnc Vue/TS files. Both Claude Code and Cursor use the same Python script with tool-specific output formatting.

**Hook pipeline:**

| Step | Detail |
| --- | --- |
| Trigger | `Edit` or `Write` on a file under `app.rnc/src/` with extension `.vue`, `.ts`, or `.tsx` |
| Script | `cortex/hooks/inject-rules-context.py` |
| Walk | From the edited file's folder up to `app.rnc/src/`, collecting every `RULES.md` |
| Priority | Closer files override parent rules |
| Limit | Output truncated at 9,000 characters with a pointer to read full files |

**New rule files:**

| Rule | Scope | Purpose |
| --- | --- | --- |
| `celer-07-view-patterns` | `app.rnc/**/*.vue`, `app.rnc/**/*.ts` | Page layout, `MMainDialog`, form molecules, granular list updates, permissions, confirm popups. Reference: `UsersView.view.vue` |
| `celer-08-rules-auto-injection` | same | Documents hook behaviour and agent obligations |
| `celer-folder-mmaindialog` | `app.rnc/src/components/molecules/MMainDialog/**` | Summary of `MMainDialog/RULES.md` — mask scoping, i18n titles, `isFooterless` |
| `celer-folder-thelayout` | `app.rnc/src/layouts/TheLayout/**` | Summary of `TheLayout/RULES.md` — containing block, `main-scroll`, `#pageHeader` |
| `celer-folder-thepageheader` | `app.rnc/src/components/unique/ThePageHeader/**` | Summary of `ThePageHeader/RULES.md` — i18n title, hamburger breakpoints, `#actions` |
| `celer-folder-form-molecule` | `app.rnc/src/modules/**/components/molecules/MAddEdit*/**` | Form molecule contract — owns form + API, emits `@onClose` / `@onSuccess` |
| `celer-folder-users-view` | `app.rnc/src/modules/User/views/Users/**` | Summary of `UsersView/RULES.md` — reference CRUD view |
| `celer-folder-theme` | `app.rnc/src/styles/theme/**` | Summary of `theme/RULES.md` — brand hex contract (`#c0e021`, `#888b8d`, `#141413`) |

`celer-folder-*` rules exist in `.cursor/rules/` only (glob-loaded when matching files are open). Claude Code receives the same folder contracts via hook injection on Edit/Write; global rules `celer-01`–`celer-08` exist in both `.claude/rules/` and `.cursor/rules/`.

**Checklist updates:** `celer-01-vue-checklist` now includes a View Patterns section and Quick Ref rows; step 1 documents auto-injection.

### Why

Folder `RULES.md` files capture intentional decisions that global conventions cannot express. Agents were inconsistent about reading them before editing. Auto-injection on Edit/Write guarantees the full chain is in context; glob-scoped `celer-folder-*` rules provide the same summaries during read/planning sessions in Cursor.

Scoped to `app.rnc/src/` only — no cross-project paths from other repos.

### Files affected

**Cortex (shared hook logic):**
- `cortex/hooks/inject-rules-context.py`
- `cortex/hooks/pre-edit-rules-claude.sh`
- `cortex/hooks/pre-edit-rules-cursor.sh`
- `cortex/generate-component-rules.md` — documents auto-injection

**Claude Code:**
- `.claude/hooks/pre-edit-rules.sh`
- `.claude/settings.json` — PreToolUse hook on `Edit|Write`
- `.claude/rules/celer-01-vue-checklist.md`
- `.claude/rules/celer-07-view-patterns.md`
- `.claude/rules/celer-08-rules-auto-injection.md`

**Cursor:**
- `.cursor/hooks.json`
- `.cursor/hooks/pre-edit-rules.sh`
- `.cursor/rules/celer-01-vue-checklist.mdc`
- `.cursor/rules/celer-07-view-patterns.mdc`
- `.cursor/rules/celer-08-rules-auto-injection.mdc`
- `.cursor/rules/celer-folder-*.mdc` (6 files)
- `.cursor/rules/generate-component-rules.mdc`

---

## 2026-05-22 — Folder-specific `RULES.md` rollout (app.rnc)

### What changed

Added 51 `RULES.md` files across `app.rnc/src/`, giving each significant folder a machine-readable contract for AI agents. Files capture folder purpose, intentional design decisions, prop/emit contracts, edge cases, and "do not" rules — never repeating what is already in global `celer-01`–`celer-06` conventions.

Agents read the chain manually during planning; from 2026-05-26 onward the pre-edit hook injects it automatically on Edit/Write (see entry above).

### RULES.md by folder

**App root**

| Path | Covers |
| --- | --- |
| `app.rnc/src/RULES.md` | `App.vue` — global Toast, no layout here |

**Shared infrastructure**

| Path | Covers |
| --- | --- |
| `app.rnc/src/components/RULES.md` | Atomic design tiers (A/M/O), auto-import contract |
| `app.rnc/src/composables/RULES.md` | Shared composable patterns |
| `app.rnc/src/helpers/RULES.md` | Helper utilities and providers |
| `app.rnc/src/stores/RULES.md` | Pinia store conventions |
| `app.rnc/src/locales/RULES.md` | Locale file structure, key ordering, `npm run sort-locales` |
| `app.rnc/src/modules/RULES.md` | Module folder structure, barrel exports, alias usage |

**Layouts**

| Path | Covers |
| --- | --- |
| `app.rnc/src/layouts/TheLayout/RULES.md` | Authenticated shell — `transform-[translateZ(0)]`, `main-scroll`, `#pageHeader` slot |
| `app.rnc/src/layouts/TheCenteredLayout/RULES.md` | Auth/marketing centered layout |

**Theme**

| Path | Covers |
| --- | --- |
| `app.rnc/src/styles/theme/RULES.md` | Brand palettes (`primary`, `surface`, `ink`), semantic tokens, forking guide |

**Components — atoms**

| Path | Covers |
| --- | --- |
| `app.rnc/src/components/atoms/AFormError/RULES.md` | Form error display |
| `app.rnc/src/components/atoms/ALogo/RULES.md` | Logo component |

**Components — molecules**

| Path | Covers |
| --- | --- |
| `app.rnc/src/components/molecules/MConfirmPopup/RULES.md` | Destructive confirm wrapper |
| `app.rnc/src/components/molecules/MDefaultModal/RULES.md` | Legacy/default modal |
| `app.rnc/src/components/molecules/MMainDialog/RULES.md` | Bottom-sheet add/edit dialog — mask scoping, i18n, scroll lock |
| `app.rnc/src/components/molecules/MOrderBy/RULES.md` | Sort selector |
| `app.rnc/src/components/molecules/MSearch/RULES.md` | Search input |
| `app.rnc/src/components/molecules/MWalletCard/RULES.md` | Wallet summary card |

**Components — organisms**

| Path | Covers |
| --- | --- |
| `app.rnc/src/components/organisms/OWalletTransactions/RULES.md` | Transaction list |

**Components — unique / navbar**

| Path | Covers |
| --- | --- |
| `app.rnc/src/components/unique/ThePageHeader/RULES.md` | Page header row, i18n title, hamburger |
| `app.rnc/src/components/unique/TheNavbar/RULES.md` | Navbar shell |
| `app.rnc/src/components/unique/TheNavbar/components/organisms/ONavbarDesktop/RULES.md` | Desktop sidebar |
| `app.rnc/src/components/unique/TheNavbar/components/organisms/ONavbarDrawer/RULES.md` | Tablet drawer |
| `app.rnc/src/components/unique/TheNavbar/components/organisms/ONavbarMobile/RULES.md` | Mobile bottom bar |

**Modules — Auth**

| Path | Covers |
| --- | --- |
| `app.rnc/src/modules/Auth/RULES.md` | Auth domain — login flows, roles, permissions, services |
| `app.rnc/src/modules/Auth/layouts/RULES.md` | Auth-specific layouts |
| `app.rnc/src/modules/Auth/views/Login/RULES.md` | Login view |
| `app.rnc/src/modules/Auth/views/SignUp/RULES.md` | Sign-up view |
| `app.rnc/src/modules/Auth/views/ForgotPassword/RULES.md` | Password reset |
| `app.rnc/src/modules/Auth/views/Terms/RULES.md` | Terms view |
| `app.rnc/src/modules/Auth/views/RolesAndPermissions/RULES.md` | Role/permission management |
| `app.rnc/src/modules/Auth/components/molecules/MLoginForm/RULES.md` | Login form router |
| `app.rnc/src/modules/Auth/components/molecules/MLoginEmail/RULES.md` | Email login step |
| `app.rnc/src/modules/Auth/components/molecules/MLoginPhone/RULES.md` | Phone login step |
| `app.rnc/src/modules/Auth/components/molecules/MLoginCode/RULES.md` | OTP code step |
| `app.rnc/src/modules/Auth/components/molecules/MLoginUsernamePassword/RULES.md` | Username/password step |
| `app.rnc/src/modules/Auth/components/molecules/MLoginPasswordCredentials/RULES.md` | Password credentials step |
| `app.rnc/src/modules/Auth/components/molecules/MSignUpUser/RULES.md` | Sign-up form |
| `app.rnc/src/modules/Auth/components/molecules/MSupportForgotPassword/RULES.md` | Forgot-password support |
| `app.rnc/src/modules/Auth/components/molecules/MAddEditProfileForm/RULES.md` | Profile edit form molecule |
| `app.rnc/src/modules/Auth/components/molecules/MPermissionPanel/RULES.md` | Permission panel |
| `app.rnc/src/modules/Auth/components/molecules/MPermissionToolbar/RULES.md` | Permission toolbar |
| `app.rnc/src/modules/Auth/components/molecules/MRolesAndPermissionsSkeleton/RULES.md` | Loading skeleton |

**Modules — Home**

| Path | Covers |
| --- | --- |
| `app.rnc/src/modules/Home/RULES.md` | Home module domain |
| `app.rnc/src/modules/Home/views/Home/RULES.md` | Home dashboard view |
| `app.rnc/src/modules/Home/views/Finance/RULES.md` | Finance/wallet view |
| `app.rnc/src/modules/Showcase/views/Showcase/RULES.md` | UI showcase view (moved to own module 2026-05-26) |

**Modules — User**

| Path | Covers |
| --- | --- |
| `app.rnc/src/modules/User/RULES.md` | User domain — CRUD services, `useUserStore`, permissions |
| `app.rnc/src/modules/User/views/Users/RULES.md` | User list view — reference CRUD pattern, granular updates |
| `app.rnc/src/modules/User/components/molecules/MAddEditUserForm/RULES.md` | User add/edit form molecule |
| `app.rnc/src/modules/User/components/molecules/MUserCard/RULES.md` | User card in list grid |

### Why

Global Vue/TS rules (`celer-01`–`celer-06`) cannot capture per-folder intentional decisions (e.g. why `MMainDialog` uses global styles, why UsersView inlines its skeleton). `RULES.md` files close that gap without bloating global rules.

### Files affected

- 51 files under `app.rnc/src/**/RULES.md` (see table above)
- No changes to `.claude/` or `.cursor/` in these commits — distribution rules added 2026-05-26

---

## 2026-05-05 — Design system rules and review command (app.rnc)

### What changed

- Added `celer-06-design-system` — typography, spacing, radius, button severity, dialog usage
- Expanded color token rules in `celer-01-vue-checklist` (brand palettes, semantic states, no hardcoded hex)
- Added `/reviewDesignConventions` command — plan-mode design system review against `celer-06`
- Updated `explain-rnc` command descriptions

### Why

Design token migration (primary/surface/ink semantic palettes) needed enforceable rules separate from script/template conventions. A dedicated review command catches violations before PR.

### Files affected

- `.cursor/rules/celer-06-design-system.mdc`
- `.claude/rules/celer-06-design-system.md`
- `.cursor/rules/celer-01-vue-checklist.mdc`
- `.claude/rules/celer-01-vue-checklist.md`
- `.claude/commands/reviewDesignConventions.md`
- `.cursor/rules/reviewDesignConventions.mdc`
- `.claude/commands/explain-rnc.md` (via related commit)
- `.cursor/rules/explain-rnc.mdc` (via related commit)
- `app.rnc/src/styles/theme/RULES.md` (theme docs consolidated in same period)

---

## 2026-05-01 — `generateComponentRules` command and checklist update (app.rnc)

### What changed

- Added `/generateComponentRules` command and matching Cursor rule — prompts agents to write a `RULES.md` for any folder in `app.rnc/src/`
- Added canonical prompt at `cortex/generate-component-rules.md`
- Updated `celer-01-vue-checklist` step 1: check for `RULES.md` in folder chain before editing
- ESLint: same-name `v-bind` shorthand (`vue/v-bind-style`) and template attribute ordering updates

### Why

Folder rules need a repeatable generation workflow so new components get documented consistently. The checklist step ensures agents look for existing rules before writing code.

### Files affected

- `cortex/generate-component-rules.md`
- `.claude/commands/generateComponentRules.md`
- `.cursor/rules/generate-component-rules.mdc`
- `.cursor/rules/celer-01-vue-checklist.mdc`
- `.claude/commands/reviewVueConventions.md`
- `app.rnc/eslint.config.ts` (ESLint rule updates)

---

## 2026-04-30 — Unified `class` / `:class` binding rule (app.rnc)

### What changed

Added a CRITICAL rule to app.rnc template conventions: when an element has BOTH static `class` and bound `:class`, they MUST be unified into a single `:class` array. The static string becomes the first array entry; dynamic parts (objects, conditional strings, icon vars) follow.

Single-source attributes are unaffected — `class="..."` alone or `:class="..."` alone stay as is. The rule only triggers when both would otherwise coexist on the same element.

### Why

While refactoring `TheNavbar` into organism components, several `<a>` and `<span>` elements ended up with both `class="..."` and `:class="{ ... }"` after adding active-state styling. Splitting the binding made diffs noisier and made the static portion harder to scan against the dynamic conditions. Unifying into one `:class` array keeps the entire class surface in one place and matches how the rest of the codebase already handles mixed bindings.

### Files affected

- `.cursor/rules/celer-04-vue-template.mdc` — new "Unified class binding" section
- `.claude/rules/celer-04-vue-template.md` — same section, expanded examples
- `.cursor/rules/celer-01-vue-checklist.mdc` — checklist item + Quick Ref row
- `.claude/rules/celer-01-vue-checklist.md` — checklist item + Quick Ref row

---

## 2026-04-28 — Commands and cleanup additions

### Added `/create-pr` command

Generates a PR title and description from the current branch diff following shared commit conventions. Available in both Claude Code and Cursor. Output is copy-paste ready — no GitHub connection required.

**Files:** `.claude/commands/create-pr.md`, `.cursor/rules/create-pr.mdc`

### Removed redundant vitrum AI folders

`vitrum/.claude/` and `vitrum/.cursor/` deleted — with rules living at the monorepo root, per-module AI folders serve no purpose.

### `start-session.mdc` model check

Added a model check at the top of the Cursor `start-session` rule. If the active model is not premium, the AI will flag it and suggest switching before loading rules.

**File:** `.cursor/rules/start-session.mdc`

---

## 2026-04-28 — Full AI infrastructure implemented at monorepo root

### Decision: monorepo-always workflow

rnc projects are always started from the monorepo root. Individual modules (`api.rnc/`, `app.rnc/`, etc.) are never forked standalone. This eliminated the need for per-module AI tooling copies and for cortex rule sub-folders.

**Consequence:** All AI rules, skills, agents, commands, and hooks now live exclusively at the rnc root. Cortex no longer holds rule files — it holds history, prompts, and documentation.

### What was built

**Root `.claude/` structure:**
- `rules/` — 7 glob-routed rule files (api.rnc architecture, app.rnc Vue/TS × 5, shared conventions)
- `settings.json` — shared team permissions and hooks configuration
- `hooks/post-edit.sh` — runs Pint automatically after PHP edits in api.rnc/, ESLint after Vue/TS edits in app.rnc/
- `hooks/pre-bash-guard.sh` — blocks dangerous bash patterns
- `skills/arcus-laravel-best-practices/` — Laravel best practices (20 rule files)
- `skills/arcus-pest-testing/` — Pest 4 testing patterns
- `skills/arcus-tailwindcss/` — Tailwind v4 patterns
- `skills/celer-advanced-patterns/` — Advanced Vue 3 composable, store, and form patterns
- `agents/code-reviewer.md` — read-only PR review agent
- `agents/security-auditor.md` — security scan agent
- `commands/reviewVueConventions.md` — review app.rnc Vue/TS convention violations
- `commands/reviewArcusCode.md` — review api.rnc PHP convention violations
- `commands/sync-cortex.md` — sync cortex prompts to distribution targets

**Root `.cursor/` structure:**
- `rules/project-context.mdc` — always-loaded Cursor equivalent of CLAUDE.md, includes rule router for planning sessions
- `rules/shared-conventions.mdc` — always-loaded git/commit/PR conventions
- `rules/arcus-api-architecture.mdc` — scoped to `api.rnc/**`
- `rules/celer-01` through `celer-05.mdc` — scoped to `app.rnc/**/*.vue, app.rnc/**/*.ts`

**Root level:**
- `CLAUDE.local.md` — gitignored personal overrides template
- `.gitignore` updated to exclude `CLAUDE.local.md` and `.claude/settings.local.json`
- `CLAUDE.md` updated with full AI tooling reference and session start instructions

**Cortex:**
- `start-session.md` — copy-paste planning prompt for Claude and Cursor
- Rule sub-folders (`vue-rules/`, `laravel-rules/`, `astro-rules/`) deleted

### Glob routing

Each rule activates only when the edited file path matches:

| Rule file | Activates for |
| --- | --- |
| `arcus-api-architecture.md` | Any file in `api.rnc/**` |
| `celer-01` through `celer-05` | `app.rnc/**/*.vue`, `app.rnc/**/*.ts` |
| `shared-conventions.md` | Always (alwaysApply: true) |

### Planning session gap

During planning (no files open), glob rules don't activate. Solved two ways:
1. `project-context.mdc` in Cursor rules instructs the AI to proactively load the right rules when you name the project
2. `cortex/start-session.md` provides a manual copy-paste prompt for both Claude and Cursor

---

## Template for future entries

```markdown
## YYYY-MM-DD — Title

### What changed
- ...

### Why
- ...

### Files affected
- ...
```
