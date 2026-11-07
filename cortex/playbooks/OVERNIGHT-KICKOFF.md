# Overnight build — kickoff kit

> Copy, fill the `[brackets]`, paste. Pairs with `OVERNIGHT-BUILD.md` (the process) and the
> `midnight-oil` skill (crash survival). Distilled from lifeshifter's kit.

## 1. Terminal, before starting the session

```bash
# keep the machine and the display awake for the whole run (browser QA needs the screen on)
caffeinate -dims -t [SECONDS] &

# the playbook lives in the target project (every child of rnc has cortex/)
mkdir -p [PROJECT_PATH]/cortex/playbooks && cp [PENDULUM_PATH]/cortex/playbooks/OVERNIGHT-BUILD.md [PROJECT_PATH]/cortex/playbooks/

# the app dev server and the API are up, the browser is open with the Claude extension, the tree is clean
cd [PROJECT_PATH] && git status --short
claude
```

## 2. The first message (one line per paragraph — do not wrap)

```text
Read cortex/playbooks/OVERNIGHT-BUILD.md end to end before doing anything else — it is the process you will follow, including its traps. Then run /battoni-start-session for [API + APP].

The job: build [WHAT TO BUILD — one paragraph: the feature or system, who uses it, what it replaces]. The reference for what it must do is [SPEC / DOC / URL — if it is a page behind a login, it is open in the browser; read it with the browser extension, a plain fetch only gets the shell]. Build it right for this codebase, not a port of the old tool's limitations.

Work on a new branch [feature/NAME]. Make the decisions you need and list them in the morning report. The machine stays awake. Set up a /loop every [N] minutes whose prompt says: keep going with the build on [feature/NAME], read the step plan in the scratchpad, review the last completed step, fix what is wrong, continue with the next; do not stop until every step is done, tested in the browser, and the morning report is delivered.

Work like this: discover first (pattern digests of one existing module per side into the scratchpad), then write the contract (data model, endpoints with exact JSON, the views), the plan file with numbered steps, a deterministic on-demand demo seeder shaped like real data, and a brief for the builders with the conflict rules. Build the foundation with one agent, then fan out builders in parallel, one per view, with i18n written to side files and merged by you. After every step: read the builder's report, read the files where bugs hide, run the gates, and test the real thing in the browser — create, toggle, save, reload — reading network and console.

You may write to the local database and log in: use a local-only dev-login route instead of typing a password, and remove it before committing. The seeded account is [USERNAME]; the app runs at [APP_URL]; the API at [API_URL]. Commit in coherent slices with messages that explain why; do not push.

In the morning: [REPORT — e.g. an artifact with real screenshots of every feature, the decisions taken, what was left out and why, how to run it], delivered by [CHANNEL]. Then memory notes for the next session, dev-login removed, git status clean, loop stopped. You have [HOURS] hours.
```

## 3. Slots

| Slot | Fill with |
| --- | --- |
| `[SECONDS]` | the run length plus margin — `36000` is ten hours |
| `[PROJECT_PATH]` / `[PENDULUM_PATH]` | absolute paths |
| `[API + APP]` | the project's two sub-projects, e.g. `api.<project> + app.<project>` |
| `[WHAT TO BUILD]` | one paragraph, in product words |
| `[SPEC / DOC / URL]` | the source of intent — a plan file, a doc, a page |
| `[feature/NAME]` | the branch |
| `[USERNAME]` / `[APP_URL]` / `[API_URL]` | the local environment |
| `[REPORT]` / `[CHANNEL]` | what the owner wants to find, and where — never a secret pasted into the message; name where the key lives |
| `[HOURS]` | the budget |

## 4. While it runs (from another terminal)

```bash
git -C [PROJECT_PATH] log --oneline -5          # slices landing
git -C [PROJECT_PATH] status --short | wc -l      # files in flight
cat [SCRATCHPAD]/PLAN.md                          # the plan file: status and log
```
