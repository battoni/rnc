# Grill Me

> Act as a demanding tech lead. The developer gives you a **difficulty** and a short **task description**; you load the relevant rules, then **grill them with questions scaled to the difficulty** until you can produce a solid, validated, checklist-backed implementation plan.
>
> **Runs in PLAN MODE. You implement nothing here** — the only deliverable is a plan presented for approval.
>
> **Usage:** `/battoni-grill-me HARD add a bulk-archive action to the Users table`
> (difficulty = `SIMPLE` | `MEDIUM` | `HARD`; difficulty and/or description may be omitted — you'll ask for them.)
> Run from the rnc monorepo root.
>
> **Which track:** this command serves the **small track** (a bug or one rule that changes; see
> `.claude/feature-flow.md`). The plan it produces must list the tests that prove it, and the PR
> body opens with a five-line intent (problem · outcome · constraints · proof). The ID and branch
> come from `/battoni-intent` (it stops after claiming them for a small task). A **feature** is
> grilled inside `/battoni-spec <id>`, which records every answer in the feature's `spec.md`.

---

## Step 0 — Plan mode

Switch to plan mode if you aren't already. **Do not create or edit any file** during this command. The only output is a plan via `ExitPlanMode`.

## Step 1 — Intake

From the arguments, read the **difficulty** (`SIMPLE`/`MEDIUM`/`HARD`) and the **task description**. If either is missing, ask:

> How hard is this — **SIMPLE, MEDIUM, or HARD**? And in a sentence or two: what do you want to build, and what does "done" look like?

## Step 2 — Load context (before grilling)

- **Rules:** if they aren't loaded yet, load them — run `/battoni-start-session` for the sub-project(s) the task touches (`app.rnc` / `api.rnc`), or read `.claude/rules/celer-*` / `.claude/rules/arcus-api-architecture.md`.
- **Local rules:** identify the module/units the task touches and read the **folder `RULES.md` chain** there, plus any rule you judge relevant. If you can't yet tell which units it touches, that's your **first** question.
- **Know the gotchas** so your questions are sharp:
  - **app.rnc:** components under `src/modules/*`, `src/components/*`, `src/layouts/*` are **auto-imported** (no import line); import from the module **barrel** (`@AuthModule`), never a sub-path; create/edit forms are extracted molecules (`MAddEdit{Entity}Form`) opened in `MMainDialog` (never raw `<Dialog>`); lists update **granularly** from the API response (no full refetch); destructive row actions use `useConfirm()` + `<ConfirmPopup>`; permissions are `canX` computeds from `userStore.hasPermission('resource.action')`; role color tokens flip light↔dark (never raw palettes).
  - **api.rnc:** the request flow is Route → Request → Controller → DTO → Service → Repository → `ApiResponse` + Resource; repository methods are semantic and entity-prefixed; mutating endpoints return the affected resource for granular frontend updates.

## Step 2.5 — If this is a FIX: reproduce it first (root cause, not band-aid)

If the task is **fixing a bug / something broken** (not building new), **reproduction comes before planning** — you can't fix what you can't reproduce, and reproducing is how you separate a **root cause** from a symptom.

0. **Ask where it happened — PROD or local?** This changes how much you can trust the data. Local data is seeded/hand-made and may not mirror the record that actually broke in production. For a prod bug, reason from the **code path + the prod symptom** (the reporter's steps, error output), not from a local row you can't prove is representative.
1. **Pin the symptom precisely** (grill): what exactly is wrong, in **which view/endpoint/action**, for **whom** (role / permissions / data), and **when** (always / only under some condition).
2. **Produce a UI reproduction guide** — a **clean, numbered** list of steps a **normal user follows in the UI** to trigger the bug. Through the interface only — never a raw API call or hand-edited DB row as the repro. Include the **starting state** (which login / record), the exact **clicks/inputs**, and **observed vs expected**.
3. **Confirm it reproduces** before going further. If you can't (e.g. a prod bug whose data isn't local), say so and reason from the code path + symptom — don't guess a fix.
4. **Target the root cause, not the symptom.** Grill toward *why* it happens (the underlying state / logic / contract). A change that hides the symptom — a guard, a fallback, a `?? null`, a `try/catch` swallow — **without** addressing the cause is a **band-aid: reject it** and name the real fix.

Carry the **reproduction steps + the root cause** into the plan.

## Step 3 — Grill (scaled to difficulty)

Ask **focused** questions, in rounds, until you could implement the task with **zero guesswork**. One concern per question. Challenge vague answers ("which view exactly?", "what does that endpoint return?").

**Always ask via the `AskUserQuestion` tool — never a free-text list the developer answers by typing "1A, 2D".** Each question is presented with concrete, selectable options:

- Give each question a short `header` and 2–4 concrete options, each with a `label` + a `description` of what choosing it implies. Put your recommended option first and tag it `(recommended)` when you have a lean.
- Use `multiSelect: true` when the choices aren't mutually exclusive (e.g. "which entry points must enforce this?"); single-select otherwise.
- Batch up to **4 questions per `AskUserQuestion` call** (one round). Need more? Send another round after these answers.
- Only fall back to a plain typed question when something truly can't be optioned (e.g. "paste the endpoint's response shape").

Calibrate the depth:

- **SIMPLE — 1 short round (~1–3 questions).** Confirm: the exact unit(s) affected, the definition of done, and the one or two genuine unknowns. Don't over-ask.
- **MEDIUM — 2–3 rounds (~4–8 questions).** The above **plus**: data/contract shape (API payload, props/emits, store shape), reuse-vs-new (which existing component/pattern to extend), edge cases (loading / empty / error / permissions), and validation rules.
- **HARD — as many rounds as it takes.** All of the above **plus**: architecture trade-offs (present 2–3 options with pros/cons and a recommendation), failure modes, migration / backward-compat, performance, security & permission model, rollout (feature-flag? phased?), and what is explicitly **out of scope**. Proactively surface risks the developer hasn't mentioned.

Rules of the grill:
- If a `SIMPLE`/`MEDIUM` task reveals hidden complexity, **say so and escalate** the questioning.
- Don't pad a hard task with trivia, and don't rubber-stamp a simple one — match the effort to the real risk.
- Stop only when you can defend every design decision. **State "I have what I need to plan this" before drafting.**

## Step 4 — Produce the plan (`ExitPlanMode`)

Present a plan with these sections:

0. **(Fixes only) Reproduction & root cause** — the **UI reproduction guide** (numbered, user-facing steps), the confirmed **root cause** (the underlying *why*), and a one-line statement that the approach fixes the **cause**, not the symptom (no band-aid).
1. **Goal & scope** — what you're building, the definition of done, and what's explicitly **out of scope**.
2. **Approach** — the chosen design. For `HARD`, briefly say why it beats the alternatives you weighed.
3. **Affected units** — sub-project(s), modules, and each file/component, marked **new** or **changed**; call out any `RULES.md` (+ sibling `CHANGELOG.md`) to **add or update**.
4. **Implementation steps** — ordered bottom-up. api.rnc: enums/migrations/models → DTOs → repositories → services → requests/controllers/resources. app.rnc: enums/types → services → stores → composables → atoms → molecules → organisms → views/routes.
5. **Convention checklist** — the **specific** `celer-*` / `arcus-*` / folder-`RULES.md` rules this work must honour (concrete, never generic): e.g. form extracted to `MAddEdit{Entity}Form` in `MMainDialog` with `:key`; granular ref add/replace/remove from the API response; `canX` permission computeds from `userStore.hasPermission`; api.rnc request-flow layering; role color tokens.
6. **Edge cases & acceptance checklist** — loading / empty / error / permission states, plus each acceptance criterion as a `- [ ]` checkbox.
7. **Validation (after implementation)** — how to prove each acceptance item is met, then run `/battoni-pre-commit` — the mechanical gate (whatever the repo's `make pre-commit` chains; with an app and an API: cursor-mirror parity, lint/format/locales, full test suite) plus the convention reviews where the repo has them: `/battoni-review-vue-conventions` + `/battoni-review-design-conventions` for app.rnc, `/battoni-review-arcus-code` for api.rnc. Where units are versioned (a `RULES.md` with a sibling `CHANGELOG.md`), bump the version + `CHANGELOG.md` for every touched one (see the `cortex-sync` skill); a repo without versioned units has nothing to bump.

8. **Decisions closed** — in the feature track these are `D#` lines in `spec.md` (`/battoni-spec`). Otherwise, when the project keeps a product memory (`cortex/plans/*product-memory*.md`, shape in `cortex/playbooks/planning/PRODUCT-MEMORY.md`), list every decision this grill closed as the next `D<n>` entries for its Decisions Log: the decision in one line, who decided and when, what it means for the build, and which `⚠️ OPEN #n` it closes. The grill writes nothing itself; the entries are recorded when the plan is approved, so a later phase spec cites `D<n>` instead of re-arguing it.

Present this via `ExitPlanMode` for approval. **Do not implement** — once approved, the developer builds it, then runs the normal flow (`/battoni-pre-commit` → `/battoni-commit` → `/battoni-create-pr` → `/battoni-publish-pr`).
