---
outline: deep
---

# Workflow

## Notion

We use Notion to manage our tasks.  
Each task will have an ID for easy reference later.

These are the statuses a task can have and a short description of their purposes:

### Grooming Stage

- New 🆕
- Needs Clarification 🤔
- Needs Scope 📝

### Ready to Be Worked On

- Task Created 📋
- On Hold 🕑

### Working

- In Progress 👨‍💻
- Ready For Review 🔍

### Testing

- Ready QA Test ☑️
- Needs Improvement 🪛

### Shipping

- Pending Deploy [DEV] ⏳
- Pending Release [PROD] ☑️

### On Production

- Done 🏁

## GitHub

Here is the convention to create a branch:

```
{taskId}-{type}/{shortDescription}
```

Example:
A task to implement Donation at the shopping cart flow.

```
TASK-999-feat/add-standalone-donation-to-cart-flow
```

Locally, this is a good example of a day-to-day flow:

1. Understand the task scope
2. Sync your local branch with `development`
3. Create a new branch for the task
4. Develop the task
5. Build it locally
6. Test to make sure everything is working as expected
7. If it's a small task, take a screenshot of the result. If it's not, record a short Loom explaining the result.

### When it's time to ship

Once your **manual review** is done, an AI-assisted flow takes you from a clean working tree to a draft PR. Each step is a command — run them in order, reviewing the output at every stage.

#### 1. `/battoni-pre-commit` — verify (commits nothing)

Runs the full gate so nothing broken reaches a commit:

- `make pre-commit` — the local mirror of CI: static checks (app.rnc features catalogue, aliases and type-check; api.rnc Pint and PHPStan; the docs portal and cortex checks where they apply), `.cursor` mirror parity, then lint, format and locale sorting (which fix files), then the full test suite (app.rnc unit + e2e, api.rnc pest)
- `/battoni-review-vue-conventions` and `/battoni-review-design-conventions` for app.rnc changes
- `/battoni-review-arcus-code` for api.rnc changes

It fixes what it can and reports a clear go / no-go. It **never commits**.

#### 2. `/battoni-commit` — commit following our conventions

Presents a commit plan — bottom-up, one concern per commit, conventional-commit messages — for you to approve before anything is committed. No AI authorship signature is added; the history reads as your own work.

#### 3. `/battoni-create-pr` — draft the description

Generates the PR title and description following our template, **for you to review**. It does not save, commit, or push anything — it just produces the markdown.

#### 4. `/battoni-publish-pr` — open the draft PR

Once you've approved the description, this command:

- Pushes the branch
- Opens a **draft** PR against `development`
- Requests Guilherme (`battoni`) as reviewer
- Sets the Vercel preview link

#### 5. Final review on GitHub

Review the PR one last time on GitHub. If everything looks good:

1. Leave a comment on your own PR: `Reviewed 🏁`
2. Mark the PR **Ready for review**
3. Update the status on Notion

From here forward, it's up to the reviewer to keep the task status updated. If it returns for changes, the responsibility goes back to the PR creator.

## ✅ Happy Path

1. Reviewer finishes the review, approves it, adds the `Ready QA Test ☑️` label, and updates the Notion status.
2. QA tests it locally. If all is good:
3. QA merges it, tests it on `development`, and adds the `Pending Release [PROD] ☑️` label.  
   3.1. There are usually multiple PRs, so QA repeats the flow for all of them.
4. Once everything is good, QA creates a release PR from `development` to `master`. After a final code check, QA merges it.
5. QA updates the Notion status to `Done 🏁`.

## ⚠️ Not-So-Happy Path

If changes are required or QA testing fails, these flags should be used:

- Needs Improvement 🪛

These flags should be reflected on Notion, where QA can leave a message detailing what needs to be fixed. The reviewer will also leave this feedback in the GitHub review.

When one of these flags is present, we should also add the `blocked` label to make the issue more visually clear.
