# Back-port — bringing a unit from a project into pendulum

The `sync` runs one way: pendulum → projects. There is no automatic way back, and this
document is what exists instead. Written during the first real back-port (the `Idea`
module, from lifeshifter, 2026-09-04) and revised with the numbers it produced.

Read alongside: `DESIGN.md` (the why), `SCHEMA.md` (the metadata), `RUNBOOK.md` (the way out).

---

## The premise, and why it holds

The projects follow the same structure and the same conventions. So bringing something
back **is not a text problem, it is a classification problem**: every file occupies a known
place in the shape of a module, and every place has a predictable treatment.

That is what keeps the work small. In the first port, ~150 files and 8,800 lines produced
**11 occurrences** of the product's name, of which **2** needed human judgement. The rest
was mechanical.

## The four classes

| class | what it is | treatment |
| --- | --- | --- |
| **body** | `app/Modules/<X>/`, `src/modules/<X>/`, migrations, tests | travels, with mechanical rewriting |
| **seam** | what the module needs and does not contain | **does not travel**: it is recreated in the target |
| **vocabulary** | the product embedded in the body | refused by the tool, decided by a person |
| **boundary** | a shared file the module edited | reviewed one by one |

In the first port the **boundary was zero**: outside the `.local.*` files, nothing in the
source project knew the module existed. If yours is large, stop and ask why.

---

## The refusal rule

The mechanical rewrite covers **paths** and the fields the outward trip corrupts:

```
api.<project>.test → arcus.pendulum.test        app.<project>.test → celer.pendulum.test
api.<project>      → arcus                       app.<project>      → celer
origin: <project>  → origin: pendulum            config('<project>. → config('pendulum.
```

**Any other occurrence of the product's word is REFUSED** and comes out in a report. There
is no way to tell from the text whether the word is there because of the transform or
because the subject really is the product — in lifeshifter's `src` there are 219
occurrences and only 3 are paths. A blind rewrite would corrupt 216 to fix 3.

> **Every path that moves content goes through the refusal.** In the first port I promoted
> the locale strings through a JSON merge, outside the tool, and two sentences carrying the
> product's name came in through the side door. They only turned up in a later scan.

---

## The seam list — start here

**A module is not self-contained.** The directory held 58 files; **twenty-nine points
outside it** were needed — thirteen found by a red test in the first port, five in the
second, and eight by the step 12 audit, which is the only step able to find a seam without
being bitten by it first. None of them appear in the manifest, the metadata or the module's
name.

Before moving, answer each of these:

| # | seam | how to find it |
| --- | --- | --- |
| 1 | composer dependency | `grep -h '^use ' | grep -v 'App\\'` in the module |
| 2 | npm dependency | imports that are neither relative nor `@Alias` |
| 3 | factories in `database/factories/` | `grep -o 'Database\\Factories\\\w*'` |
| 4 | personas and helpers in `tests/Pest.php` | global functions called in the tests |
| 5 | named rate limiters in `AppServiceProvider` | `grep -o "throttle:[a-z-]*"` in the routes |
| 6 | the module's own config file | `grep -o "config('\w*"` |
| 7 | environment variables | a consequence of 6 and of the project's config |
| 8 | permission groups | `grep -o "permission:[a-z.]*"` in the routes |
| 9 | API and web routes | wherever the project declared them |
| 10 | Blade views | `grep -o "view('\S*"` |
| 11 | shared components used in the template | template components that are not in the module |
| 12 | the module's alias in `vite.config.ts`, `tsconfig.app.json` **and** `vitest.config.ts` | all **three**, always |
| 13 | `components.d.ts` regenerated | a build; without it the emit types become `any` |
| 14 | the module's **i18n keys** in the target's locales | `$t('...')` in the module against `en.json` |
| 15 | migrations for the models' tables | they live outside the module; moving the directory leaves them |
| 16 | Pinia store in `celer/src/stores` | the `@Stores` alias exists and says nothing about the store |
| 17 | the module's routes mounted in the front router | the module's `routes.ts` imported in `libraries/router` |
| 18 | the module's seeder called in `DatabaseSeeder` | **never** in `seeders.local.php`, which is LOCAL |
| 19 | shared composable in `celer/src/composables` | imports coming from `@Composables` |
| 20 | **what the shared component pulls**, one level down | the module does not import it; its component does |
| 21 | nav entry — a module with a screen and no way to reach it | `routeName:` in `TheNavbar.vue` |
| 22 | the module's routes in the target's `arcus/routes/api.php` | the controllers named there, not only at the source |
| 23 | **which roles receive** the new permission (`ManagerSeeder`) | a decision, not a mechanic — but the asking is |
| 24 | `lang/<locale>/<x>.php` files for the API's messages | `__('x.y')` in the module's PHP |
| 25 | the **e-mail message** (`app/Mail/`) and its template | `new XxxMail(` in the module's PHP |
| 26 | **a new column on a SHARED table** (`users`) | by hand: no check sees this |
| 27 | a **plain helper** from `@Helpers` | the composable check only knows `use*` names |
| 28 | a **CSS custom property** the markup reads through `var(--x)` | `--x:` in the target's stylesheets |
| 29 | a hand-written **`@utility` class** the markup wears | `@utility <name>` in the target's stylesheets |

**On seams 27 to 29, added by the layout/nav port, which are one idea.** All three are a NAME
the moved markup uses and the target has to DEFINE somewhere shared. Seam 27 is the loud one:
`getLoginPath` came from `@Helpers`, and `shared_composables` was never a candidate to catch it
because that check recognises a symbol by its `use` prefix. The build failed — "is not exported
by src/helpers/index.ts" — after the whole suite had gone green. Late, but survivable.

**Seams 28 and 29 do not fail at all**, which is why they earn a row. An undefined `var(--x)`
resolves to nothing and the property falls back to its initial value; an undeclared `@utility`
class is simply never generated, so the element keeps whatever it had. The port carried
`motion-calm`, `motion-ease-settle` and `--mobile-nav-band` into a parent that declared none of
them: the rail's collapse would have animated at the browser default and the mobile bar would
have sat on top of the content, with nothing red anywhere and no line in any log. The check that
now asks this found something else on its first run — `shadow-lift` was already worn by
**fourteen** places in the parent and `shadow-sink` by three, none of which did anything, because
an earlier port brought the markup and left the utilities behind. Fourteen raised surfaces
rendering flat, and no way to notice except by looking.

Five greps answer for almost all of it. **Running them first costs minutes; finding out by
red test cost most of the time in this port.**

**On seam 25, which stays green while it is broken.** `Mail::fake()` never builds the
message, so a missing Mailable — or a template that does not exist — passes the whole suite
without a failure, and only turns up when somebody actually sends the e-mail. The Mailable
lives in `app/Mail/`, outside the module, and its template is a level deeper still; the
pre-flight now follows both hops.

**On seam 26, which stays your reading.** This port needed four new columns on `users` — a
table that already existed and is shared by everyone. No check sees that: the migration check
asks whether the MODULE's model has a table, and `users` is nobody's model.

**The column goes INTO `create_users_table`, and then `make fresh`.** I got this wrong first
time and wrote an ALTER, reasoning that children had already run the create. That reasoning is
the source project's, not the bootstrap's: pendulum keeps no data, so there is nothing an ALTER
protects and the create is the table's only description — `arcus-api-architecture` §3. A ported
module will often argue the other way in its own prose, because the project it came from had
shipped. **That premise does not travel; delete it when you see it.**

**On seam 24, which is 14 on the other side of the house.** I wrote that the i18n check
only scanned the front, and writing that protects nobody: the Tip port carried three
`__('tips.…')` across without the `lang/` files, and the parent did not even have the
directory. **Laravel does not throw on a missing translation — it returns the key itself**,
so the API was answering `tips.anchorTaken` to the client. The suite stayed green because
the tests assert on which field failed, never on what it said. A hole that is known and
written down is still a hole. (Historical: the Tip refusals have since moved to throwing the
i18n key itself — `tips.errors.anchorTaken`, translated by the SPA — and `lang/*/tips.php`
is gone. The check still stands for any `__()` a port carries across.)

**On seam 14, the most expensive of all.** A translation key the target does not have never
throws, never fails a test and never shows in type-check: the screen renders `TIPS.TITLE`
in capitals, in production, and only a pair of eyes catches it. That is how
`ROLESANDPERMISSIONS.PERMISSIONS.MANAGE` reached the parent in the Idea port with the suite
green — and when the check finally existed, it found `common.moreActions` missing in two
places **already merged**, one of them `ThePageHeader`. It was an `aria-label`: invisible on
screen, and a screen reader was saying "common.moreActions" out loud.

**On seam 12, which has bitten twice.** Three files, because each serves a different
consumer: `vite.config.ts` is the build, `tsconfig.app.json` is the type-check,
`vitest.config.ts` is the suite. Forgetting one passes the other two — the module compiles,
the editor is quiet, and the test dies in `Failed to resolve import`. The Tip port
discovered that Idea, already merged and green three times, had exactly this hole: it only
stayed hidden because no Idea test went through the router. **Green is not evidence the
alias exists; it is evidence nobody has asked for it yet.** `vitest.config.ts` declares the
alias without the `@` (`{ alias: 'TipModule' }`), so searching for `'@TipModule'` across the
three files never finds the third.

---

## The procedure

1. **Measure.** Count the occurrences of the product's name in what is about to move,
   separating paths from the bare word. The bare-word number is the size of the human
   judgement ahead. If it is large, the module is not ready to travel.

2. **Raise the seams — and this is a command, not a checklist.**

   ```bash
   python3 cortex/sync/backport_preflight.py <project> \
       api.<project>/app/Modules/<X> \
       api.<project>/tests/Feature/<X> \
       app.<project>/src/modules/<X>
   ```

   It scans the module at the source, lists everything it reaches for outside itself, and
   checks each item against the target. Exits 1 if anything is missing. **Pass the tests
   along** — they carry personas and global helpers the code does not.

   `<project>` is the child's **path** from `battoni.dev`, and it is not always its name:
   impressao lives at `impressao/new.impressao`. The API, APP and product names come from
   the child's own `.cortex-sync.conf` — the same source the sync already reads — and not
   from the folder. While they came from the folder, the `config/<project>.php` check
   simply never fired for impressao, in silence.

   Validated against this very port: run at the commit before it, it reports **17
   absences**, including the three that break no test at all — the `ThePageHeader
   #primaryAction` slot the target did not declare, the `OIdeaCaptureDialog` the source
   mounts in a layout, and the `Pest.php` personas. Run against today's state, it reports
   **zero**.

   **What it does NOT see**, and stays your reading: a dependency arriving through a peer
   (`quill` came in through PrimeVue's `Editor`, with no direct import), `components.d.ts`,
   which is a build step, and the second-level chain — it reports the missing shared
   component, not what *that* component needs in turn.

3. **Copy with the mechanical rewrite**, keeping the refusal report.

4. **Resolve the refusals one at a time**, noting the decision and the reason. There are
   few of them and each is a product choice dressed as a string.

5. **Run the suite.** Every red is a seam that was missed. Go back to step 2.

6. **Raise the seams into the core.** Everything that comes back is bootstrap: routes go to
   `routes/api.php` and `libraries/router/routes.ts`, permissions to
   `PermissionGroupsHelper`, the nav to `TheNavbar.vue`, strings to
   `locales/{en,pt-BR}.json`. The parent's `.local.*` files stay empty — they are the
   projects' extension point, not the bootstrap's.

7. **Run the seeders for real** (`php artisan db:seed`), never grant a permission by hand.
   A manual insert makes the screen open and **proves nothing**: it skips exactly the path
   you just changed. Running the seeder is what verifies that the groups were promoted into
   the right helper and that the exclusion rule leaves the ordinary role with what it
   should have and nothing more. Compare the per-role counts before and after — it is the
   cheapest test there is for a permission change.

8. **Regenerate** `components.d.ts` and the Features screen's catalogue.

9. **Green on both sides**, plus lint and type-check.

10. **DEEP DIFF against the source — and this step is not optional.**

    ```bash
    python3 cortex/sync/backport_diff.py <project> \
        api.<project>/app/Modules/<X>:arcus/app/Modules/<X> \
        app.<project>/src/modules/<X>:celer/src/modules/<X> \
        ...  # one pair per unit moved, loose files included
    ```

    It normalises the mechanical transforms on both sides and answers three questions: did
    any file fail to arrive? did any arrive different? did the target gain something the
    source never had? Exits 1 if a file was lost or gained; a difference is for a human to
    read. **Every difference has to match a decision you recorded.** One that does not is an
    accident.

    In the first port the suite was green and the screens were working when this step ran —
    and it found: the FRONT enum still carrying 9 cases while the API had 3, an interface
    string reading "Removed Shifter", six orphan area labels, and a `RULES.md` pointing at
    files that do not exist in the target. **None of that breaks a test and none of it shows
    on the screen you opened.**

11. **Run the suite AGAIN after every mechanical sweep.** The last two breakages of the
    first port were mine, caused while cleaning vocabulary: an apostrophe inside a
    single-quoted string, and a fixture title mangled by a partial replacement followed by a
    complete one. Bulk sed gets it wrong; the suite is what counts.

12. **AUDIT THE PRE-FLIGHT ITSELF — and this is a command too.**

    ```bash
    python3 cortex/sync/backport_audit.py <port-sha> <project> \
        api.<project>/app/Modules/<X>:arcus/app/Modules/<X> \
        app.<project>/src/modules/<X>:celer/src/modules/<X>
    ```

    It creates a worktree at the commit BEFORE the port, runs TODAY's pre-flight there, and
    compares the warnings with what the port's commit touched outside the module. Exits 1
    while any file is left unexplained.

    **Do not ask the tool what it thinks it covers; ask the work what it demanded.** This
    comparison is what produced seams 14 to 23 — four in the Tip port, five from crossing
    the two ports. A file that comes up blind in ONE port is a candidate; one blind in BOTH
    is a seam, and becomes a check. That is how `ManagerSeeder`, `routes/api.php` and
    `TheNavbar.vue` left the list.

    Not every blind spot is a seam: another module's view that gained the component call is
    the feature itself. The tool does not know the difference, and should not pretend to.

13. **PARTIAL port — into a module that already exists on both sides.** The diff compares
    directories, so pointing it at `Auth:Auth` would measure the whole module and bury the
    report under "PARENT ONLY" and "DIFFERS" for everything that has already diverged on its
    own. **Pass the files, not the directory** — the diff accepts a single file, and that is
    what answers the right question: did each file I meant to bring arrive whole? The
    pre-flight still takes the module's directory at the source, which is where the seams
    show.

14. **The tools have tests, and the tests have mutation.** `test_backport.py`. After
    touching a check, reintroduce the defect and confirm the suite goes red — three of my
    own "regression tests" did not bite at all, and one of them was held up by a dead branch
    in the tool itself. A test that has never been red has proved nothing.

---

## The target has defects too, and the port reveals them

A ported module exercises pendulum in a way its own tests never did. Two turned up in the
first port:

- **`RolePermission` without `$timestamps = false`.** The table has no such columns; the
  sibling model already said so, this one did not. Any `create()` would always have thrown —
  it stayed hidden because the seeder writes through `insert()`, which goes under the model.
- **The ordinary role would have received moderation.** `ManagerSeeder` gives the `user`
  role everything but a few keys; with the new group it would have gained `ideas.manage`.

**Treat this as a result, not an interruption.** Fix it in pendulum and write it down — it
is half the value of a back-port.

---

## The most expensive seam: the module needs a NEWER version of something shared

Three screens from the first port came out wrong and not one of them because of the module.
`NewsView` uses a `#primaryAction` slot and a `collapseActions` prop from `ThePageHeader` —
which **exist in the child and not in the parent**. A missing slot gives no warning: Vue
simply renders nothing, and the "New entry" button disappears with no console error at all.

And the fix pulled a **chain**:

```
Idea module → ThePageHeader (new version)
                 → useViewport (missing composable)
                     → MOBILE_BREAKPOINT_PX (missing constant)
                 → AMenuButton (missing atom)
                     → the collapsible rail  ← I CUT HERE
```

**Cut the chain at the first link that belongs to the product.** I took the child's
`ThePageHeader` but kept the parent's menu button: the child's `AMenuButton` atom also
toggles a collapsible rail the bootstrap does not have, and adopting it would have dragged
the whole navigation system across for the sake of one slot. **Port the contract the module
needs, not the component's entire lineage.**

### The warning that would have saved this

A missing slot fails in silence. Before running, list the slots and props the module uses
from shared components and check each against the target:

```
grep -rhoE '#[a-zA-Z]+' src/modules/<X> | sort -u
```

## A module can require a MOUNT POINT in a shared layout

The "New idea" button opened and nothing happened — no error, no dialog. The handler calls
`ideaStore.openCapture()`, and `OIdeaCaptureDialog` is mounted **once, in `TheLayout`**,
above every authenticated screen. Outside the module, and therefore outside the copy.

The source project had already documented the problem in the file itself: the front's two
LOCAL extension points (`routes.local.ts`, `navItems.local.ts`) carry **data, not
components**, so that line conflicted on every sync. In pendulum the module is bootstrap and
mounts directly — but the gap stands for a project wanting to mount an overlay of **its
own**. If that comes up, the answer is an overlay slot in the layout, not another edit to
the file.

**Look for this first:** `grep -rn "openCapture\|<O[A-Z]" src/modules/<X>` and check whether
each organism referenced is mounted inside the module or somewhere else.

## The scan catches the product's NAME, not its subject

After everything was green, the new-idea dialog still said *"when I lay out the week, almost
every block I place is the same as one already there"* — timeboxing, in a bootstrap
placeholder. And the area labels still listed Season, Retro and Trails, cases the reduced
enum no longer has.

The refusal looks for `lifeshifter`. None of those lines contain the word. **After the scan
by name, do one by CONCEPT** — the nouns of the source project's domain — and look at the
screens: a placeholder is product copy just as much as a title is.

## Versions do NOT order across repositories

`ThePageHeader` was at **1.2.0 in the parent** and **1.1.1 in the child** — and the child's
was functionally newer, with three slots and a prop the other did not have.

After the fork the lineages are independent: each side bumps on its own and the numbers stop
comparing. **A higher number does not mean newer.** To decide who is ahead, read both
CHANGELOGs or diff the files — never compare the integers.

The same goes for the question "does this project already have this feature, at the same
version?": the honest answer comes from the content, not from the field.

## A permission group carries vocabulary too

Promoting the `ideas` and `news` groups was not enough: the Roles screen showed
`ROLESANDPERMISSIONS.PERMISSIONS.MANAGE` raw, because the parent never had the **verb**
`manage` — only `read`, `create`, `update`, `archive`, `delete`, `search`, `resetPassword`.
**When raising a group, raise every verb the target does not have as well.**

---

## The tests cost more than the code

That was the biggest surprise. The tests carried **more product vocabulary than the code**:
personas (`shifter()`, used 83 times), the product's enum values, the product's URLs, and a
seeded role that exists only at the source. Reducing an enum from 9 cases to 3 broke eight
tests at once.

**A back-port that only looks at `app/` and `src/` sees none of this.** Move the tests
along, expect them to break, and read every break: it is almost always the product speaking.

---

## Numbers from the first port

| | |
| --- | --- |
| files moved | 155 (58 API + 97 front) |
| occurrences of the product's name | 11, of which 2 needed judgement |
| seams outside the module | 13 |
| defects revealed in the target | 2 |
| tests pendulum gained | +151 on the API, +90 on the front |
| judgement decisions recorded | 5 |
