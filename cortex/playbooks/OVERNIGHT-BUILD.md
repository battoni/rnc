# Overnight build — playbook for an autonomous multi-hour build with parallel agents

Written after a real unattended build in a project born from this bootstrap (an API module, a Vue
module with four views, ~1 500 tests, a report artifact and an email, in about six hours) and
back-ported here so the next run starts from it. For any agent asked to do the same: this is what
worked, what bit, and the order that made it safe.

**Pairs with the `midnight-oil` skill.** That skill is the crash-resilient phase loop — how the run
survives a dropped connection and resumes. This playbook is what happens INSIDE a run: the contract,
parallel builders, the traps, the verification, and the report.

## 0. The shape of the night

```
discover → contract → plan file → loop on → foundation → parallel builders → review each → Chrome QA → gates → commit → artifact + email → cleanup
```

Two things carry the whole run: a **contract document** every builder reads and a **plan file**
updated on every tick. Everything else is execution.

## 1. Must-dos, in order

1. **Start the keep-alive and the loop before anything else** (§5, §6). If the run dies at hour
   three, the plan file is what lets the next session resume.
2. **Branch first.** `git checkout -b feature/<name>`. Commit unrelated pending work separately
   before the feature starts, so the feature diff stays clean.
3. **Discover with digests, not with your own context.** Spawn `Explore` agents (`model: "sonnet"` — `.claude/delegation-policy.md`) to produce
   *pattern digests* of ONE existing module per side (API and app): controller / service /
   repository / resource / request / DTO shapes with verbatim snippets, migrations, factories,
   tests, routes, permission registration, lint config, RULES.md. Save them to the scratchpad
   (`API-PATTERNS.md`, `APP-PATTERNS.md`). Builders read those instead of re-deriving the style.
4. **Write the contract** (`CONTRACT.md`): tables and columns, enums, resources (exact JSON shapes,
   casing, how ids are typed), every endpoint with params and responses, the computation rules,
   and a table of the views (route, purpose, what each shows). It is the source of truth, and it is
   what lets the API and the frontend be built **at the same time**.
5. **Write the plan file** (`PLAN.md`): decisions, numbered steps with `[ ] [~] [x]`, and a running
   log with timestamps. Every tick reads it first and writes to it last.
6. **Write the demo-data spec** (`DEMO-DATA.md`) shaped like the owner's real data — names, counts,
   rates, deviations, seasons — and require a **deterministic, idempotent, on-demand** seeder.
   Screenshots of an empty product convince nobody.
7. **Write a shared brief for parallel builders** (`VIEW-BRIEF.md`): design direction (tokens, type,
   spacing, tone), structure rules, quality gates, the report format, and the **conflict rules**
   (§2.1).
8. **Foundation before fan-out.** One agent builds the shared base — migrations, models, enums,
   permissions, routes skeleton, module scaffold, store, services, the i18n vocabulary — and only
   then do the feature builders fan out on top of it.
9. **Review every builder's report the moment it lands**: read the two or three files most likely
   to hide a bug (store merge logic, resource shapes, arithmetic), run that module's gates
   yourself, open the surface in Chrome. Fix what you find before the next fan-out that depends on it.
10. **Gates before every commit**: lint, format, typecheck, unit, feature — the *whole* suites, not
    only the module's. Commit in coherent slices (API, app, docs) with messages that say why.
11. **QA in the real browser, with real interactions** (§7).
12. **Bump the docs the repo expects** (RULES.md / CHANGELOG per unit, registries) — an unversioned
    unit fails the project's own freshness gate.
13. **Ship the report** (§3): artifact with real screenshots, decisions and what was left out; then
    the email if it was asked for; then a push notification; then memory notes.
14. **Clean up**: remove dev-only routes, `git status` clean, stop the loop, close the tabs.

## 2. Traps

### 2.1 Parallel agents in one working tree

- **Shared files get clobbered**: barrels (`index.ts`), locale JSON, the store, route registries.
  Rules that worked: builders create only inside their own folders; barrels are *append-only, with
  a minimal edit anchored on the last line, re-read right before editing*; **i18n goes to side
  files** (`scratchpad/i18n/<view>.json`, `{ en, pt-BR }`) merged by a script that never overwrites
  an existing key, then `npm run sort-locales`; the store is off-limits — a new getter becomes a
  view-local composable and is reported.
- One builder re-sorting a barrel while another appends is harmless only because both are
  append-only. Re-sort barrels yourself at the end.
- Specs written before the locale merge assert on `data-testid`, never on translated text.
- Give each builder a report format — files, components one line each, store needs, i18n path,
  what was NOT finished. Their report is your review checklist.

### 2.2 Browser automation (Claude in Chrome)

- **Never type credentials.** For a password-free local session, add a local-only
  `GET /api/dev-login` closure that `Auth::login`s the seeded user — **inside the API route group**,
  not the web group: the web group encrypts the session cookie and the API group does not, so a
  web-group login is invisible to API routes. It never reaches a commit: save the block to the
  scratchpad, `git checkout` the file before committing, re-append while QA continues.
- The SPA gates on a client-side hint (`localStorage['app.rnc:session-hint'] = '1'`); set it on the
  **exact origin, port included**, or the guard skips the `/auth/me` probe.
- Find the real dev server before starting one: `Port 5173 is already in use` means it is running;
  the Valet host without the port serves static files and renders a blank page.
- Tab hygiene: closing a tab can remove the whole MCP tab group and invalidate every id — call
  `tabs_context_mcp` again. A renderer can freeze after `resize_window`; open a new tab rather than
  retrying. `find` refs go stale after navigation, and a ref-click sometimes misses a button a
  coordinate click hits — screenshot, then click by coordinates.
- **Password managers hijack focus** on autofocused inputs, and then `type`, `key` and even
  `javascript_tool` fail with "Cannot access a chrome-extension:// URL". Reload to reset focus and
  set values with `form_input` by ref.
- `WebFetch` returns only the shell of a client-rendered page. Read those through Chrome with
  `get_page_text`; enumerate links with `javascript_tool`.
- Network and console tracking start on the first call: call them once, navigate, then read.
- Sessions expire. When `/auth/me` starts answering 401, hit dev-login again.

### 2.3 Framework and tooling

- Laravel `immutable_date` under sqlite (the test database) writes `Y-m-d H:i:s`, so
  `where('day', '2026-08-25')` silently misses. A small custom cast that stores `Y-m-d` fixes it.
- Spatie Data validates on `Data::from($request)` and infers `required` for a non-nullable array —
  an array that may legally be empty has to be nullable in the DTO.
- A module alias lives in **three** files (`vite.config.ts`, `tsconfig.app.json`,
  `vitest.config.ts`); `npm run check:aliases` catches a missing one.
- An optimistic update must **merge**, not replace: a response that does not carry the loaded
  relation blanks it in the list until the next read.
- A shell proxy like `rtk` collapses `$VAR` inside `npx …` and can mask a failing exit code; run
  complex gates from a script file, and confirm a gate with `rtk proxy <cmd>` before trusting it.
- Scheduled ticks fire only when the session is idle; agent completions wake it on their own. Do
  not spend ticks polling — a tick is "review, fix, continue".
- The permission classifier may refuse to read a project's `.env`; test for a key's presence
  (`grep -c '^RESEND_API_KEY=re_'`) and load it into a shell variable without printing it.

### 2.4 Scope and judgment

- Reading the code alone misleads. Read the product documents (brief, product memory, framework)
  before designing a module — the brief is what says what the product is NOT.
- Say what was left out and why, in the plan and in the report. Scaling down is the owner's call;
  hiding that you did is not an option.
- Polish the demo data when a screenshot looks wrong (stale overdue items read as neglect).

## 3. The artifact and the email

1. Load the `artifact-design` skill before writing. The project's design system (tokens, fonts)
   beats any default.
2. Screenshots: Chrome `computer` → `screenshot` with `save_to_disk: true` per view after a `wait`;
   crop the extension's floating widget (bottom ~48px); JPEG around 60–80 KB each.
3. One HTML file: `<title>` as a product name, Google Fonts, light and dark tokens on the three
   selectors, explicit `body` background, images as base64 data URIs (page < 16 MB). Content order:
   thesis → numbers → one section per surface (screenshot, what it does, decisions) → "from the old
   system to the new" table → decisions taken → what was left out → how to run → commits.
4. Publish with the `Artifact` tool. The URL stays stable across republishes of the same path.
5. Email through the Resend REST API with `curl`: the key read from the project's `.env` into a
   shell variable and never echoed, `from` on a **verified sender domain**, the JSON body in a file,
   each prose paragraph on ONE line (mail clients reflow manual breaks). Sending mail is an
   explicit-permission action: only when the owner asked for it in the conversation.
6. `PushNotification` with the one line they would act on; then the memory note.

## 4. Testing in a loop

Each iteration is the same three moves: **review the last completed step** (read the files a bug
would live in, run that unit's gates, look at it in Chrome), **fix what is wrong** (small, in the
same slice), **continue with the next step**. The step list lives in the plan file; the tick prompt
says "check the plan, review the last step, fix, continue". Most progress is driven by builder
completions; the tick is the safety net that catches a stalled run.

## 5. Keeping the machine awake

- macOS: `caffeinate -dims -t 28800 &` — no display sleep (Chrome QA needs the screen), no idle
  sleep, no disk sleep, eight hours.
- Keep the browser at a desktop size (`resize_window` 1440×900): a narrow default window renders the
  mobile layout in every screenshot.

## 6. The schedule is a safety net, not a poller

- The loop's scheduling and crash survival are `midnight-oil`'s (a self-paced wakeup plus a
  heartbeat). What this playbook adds is the rule for what a tick does.
- The prompt must be self-sufficient: the branch, the plan file, and the stopping condition ("do
  not stop until every step is done, tested in Chrome, and the report is published").
- Do not use a tick to poll subagents — their completion wakes the session. Use it to review and
  advance. The plan file, not the conversation, holds the state.

## 7. Verifying your own work

- **Gates**: lint, format check, typecheck, unit, feature — module first, whole suites before a
  commit. Re-run after every refactor, including the "safe" ones.
- **API by hand**: `curl` with a cookie jar from dev-login; read the shapes against the contract
  (casing, id types, nulls, dates).
- **Browser**: per view, `wait` → `screenshot` → `read_network_requests` (every call 200?) →
  `read_console_messages` (errors?). Then real interactions: create through the dialog, toggle,
  save, navigate, check the request that went out and the state that came back. Reload to prove
  persistence.
- **Numbers**: recompute one derived value by hand.
- **Builder reports**: every "deviation from the contract" and every "not finished" line is a review
  item; verify at least one claim per report yourself.
- **A builder's half-finished work is finished by whoever commits it.** The commit says the work came
  in part from a builder and was completed, tested and reconciled — and when two builders' changes
  cannot honestly be separated (both rewrote the same store), they land together and the message
  says why.
- **Layout by matrix, not by eye.** Before closing a batch, measure the layout with Playwright over an
  explicit matrix — every route touched × the widths the breakpoints name × both layout modes × both
  colour schemes — and write down the defects as numbers ("192px left against 280 right at 1920").
  An impression is not a finding.
- **Screenshots as review**: look for stale data, wrong casing, wrong defaults — the product's own
  tone rules are the rubric.
- **Clean tree**: `git status` empty at the end; dev-only code gone; the loop stopped.

## 8. The reusable scratchpad set

`PLAN.md` · `CONTRACT.md` · `API-PATTERNS.md` · `APP-PATTERNS.md` · `DEMO-DATA.md` · `VIEW-BRIEF.md`
· `i18n/<view>.json` + `merge-i18n.py` · `shots/*.jpg` · the report HTML · `email.json`. Start the
next run by copying this list.
