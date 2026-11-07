# Create PR

Generate a pull request title and description for the current branch.

---

## Step 1 — Read the branch

Run the following in parallel:

- `git log main...HEAD --oneline` — list all commits on this branch
- `git diff main...HEAD --stat` — list all changed files
- `git diff main...HEAD` — full diff

## Step 2 — Understand the changes

Read the diff carefully. Identify:

- What the change does (the what)
- Why it was made — infer from the code, commit messages, and context (the why)
- Which sub-project(s) are affected (api.rnc, app.rnc, etc.)
- Whether it is a feature, fix, refactor, or chore
- Which files were touched (for the per-file breakdown)

## Step 3 — Generate the PR

Output the following, ready to copy-paste:

---

**Title** (`Task Title — TASK-ID` when a task ID is available, imperative mood, no period):

```text
[title here]
```

**Description:**

```markdown
# [Task Title — TASK-ID if available]

## tl;dr
- [Concise bullet points — human readable, full value. One bullet per meaningful change.]

## Links
- API PR:
- App PR:
- Notion:

## Test Cases
- [ ] [Meaningful test case based on what was touched — one per logical change]
- [ ] [Record each test in a Loom video]

## Release Notes
- [Anything devs need to do: install a package, add a .env entry, run a migration, etc.]
- [Omit this section if there are no release steps]

## Pre-merge checklist
- [ ] Ran `/battoni-pre-commit` — lint, format, locales, tests, and convention reviews all green
- [ ] Feature tested manually end-to-end
- [ ] Loom recorded
- [ ] Code reviewed after PR draft on Github
- [ ] PR tags added
- [ ] Review requested

## Details — Per file updates

### [FileName]
- **Path:** `path/to/file`
- **Change:** [Concise tl;dr of what changed]
- **Reason:** [Why this file was updated]
```

---

## Step 4 — Hand off for review

Present the generated title and description to the developer for review. **Do not save a file, commit, or push** — this command only produces the PR markdown. The developer reviews it; `/battoni-publish-pr` consumes the approved output directly.

---

## Rules

- Title uses imperative mood: `Add`, `Fix`, `Remove`, `Refactor` — not `Added`, `Fixed`
- Include the task ID in the title when one is available (e.g. `Add timer feature — CONV-42`)
- `tl;dr` bullets must be human-readable and complete — a reader unfamiliar with the code should understand the change
- Test cases follow the rule: if a file was touched, it must be tested
- Omit `## Release Notes` if there are no steps for the dev team
- One `### FileName` block per file changed — do not group multiple files together
- Do not mention file names in the title — save those for the per-file section
