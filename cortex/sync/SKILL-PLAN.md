# Skill Plan — `cortex-sync` + skill housekeeping

Planning doc. **§1 (`cortex-sync` skill) is BUILT** (2026-06-14) — see
`.claude/skills/cortex-sync/SKILL.md` + its Cursor mirror `.cursor/rules/cortex-sync.mdc`.
§2 (refresh `celer-testing`) and §3 (MCP decision) remain as written below.

## Why a skill, not just docs

The sync system lives in `cortex/sync/*.md` — but an agent only follows those if *pointed*
at them. A **skill auto-loads on the right triggers**, turning written discipline into
default behavior. And because skills are committed markdown, this one **propagates to every
project** via the sync system itself — so every project's agents inherit the same discipline.

Contrast with MCP servers (playwright-mcp, firecrawl, etc.): those are **runtime
dependencies** and must NOT be committed into pendulum — they'd force an install + auth onto
every project and collaborator. MCP servers go in personal/global config only. Skills are
the right propagating mechanism; MCP is not.

## 1. `cortex-sync` skill (✅ BUILT 2026-06-14)

**Purpose:** make any agent automatically follow the metadata + propagation discipline when
working anywhere in a pendulum-derived repo.

**Trigger (description) — load when:**
- editing/creating a unit that has (or should have) a `RULES.md` + `CHANGELOG.md`,
- changing a component/view/module/service and needing to bump version,
- running or reasoning about the sync (`sync.py`, `pendulum-upstream`, `/setup-project`),
- touching the manifest, transform, or `.cortex-*` files.

**Must encode (the rules scattered across the docs today):**
- **Triad discipline:** a unit = source + `RULES.md` + `CHANGELOG.md`; they travel together.
- **Version-on-change:** any code change to a unit → new CHANGELOG top entry + matching
  `RULES.md` `version` (SemVer; major=breaking contract, minor=additive, patch=tweak).
  CHANGELOG is canonical; frontmatter mirrors it. (Points to `SCHEMA.md`.)
- **Tests are part of the unit** (celer co-located; arcus `tests/` mirrors path) — a
  test-only change still bumps the unit (patch/minor).
- **MODE awareness:** OVERWRITE (AI layer) / MERGE (code, tests, config) / LOCAL
  (brand/identity) / SKIP. Meta-tests are pendulum-only.
- **Naming/transform:** project words map (`celer`→`app.X`…); `celer-`/`arcus-` filename
  tokens are protected; MSW/e2e URL tokens transform too.
- **Parallel-session safety:** stage by explicit path; never `git add -A`.
- **Source-of-truth direction:** improvements flow pendulum→projects; back-port is manual
  cherry-pick.
- **Pointers, don't duplicate:** link to `DESIGN.md` / `SCHEMA.md` / `RUNBOOK.md` for depth;
  the skill is the *trigger + the rules*, not a copy of the design.

**Parity:** Claude `.claude/skills/cortex-sync/SKILL.md` + Cursor `.cursor/rules/cortex-sync.mdc`.

**Scope decision (resolved):** **trigger-load**, not `alwaysApply` — avoids context cost on
unrelated work. Cursor globs: `**/RULES.md`, `**/CHANGELOG.md`, `cortex/sync/**/*`,
`**/.cortex-*`; the Claude skill `description` carries the matching triggers.

## 2. Housekeeping — align `celer-testing` with the expanded strategy (✅ DONE 2026-06-14)

The skills are already well-scoped (no overlap): `celer-advanced-patterns` = building,
`celer-testing` = testing, `arcus-pest-testing` = PHP. No split needed.

The Claude `SKILL.md` (description + body) was refreshed during the testing build to the
three-tier model; the **Cursor mirror** (`.cursor/rules/celer-testing.mdc`) had drifted and was
synced byte-for-byte to the canonical skill body on 2026-06-14. The skill now covers:
- **VTU vs Testing Library by tier** (component-contract → VTU; view/integration → TL),
- `renderWithPlugins` (TL) alongside `mountWithPlugins` (VTU),
- **MSW** for the integration tier,
- `.integration.spec.ts` naming + co-location rule,
- the JSON-reporter gotcha.
Keep Claude + Cursor mirrors in sync. (Do NOT edit while Sonnet is mid-build on it.)

## 3. MCP servers — explicitly OUT of the repo

Decision: none of the candidate MCP servers (playwright-mcp, chrome-devtools-mcp,
firecrawl, ppl-ai/perplexity, graphify) are committed into pendulum. They are runtime
dependencies; committing their config would force install+auth on every downstream project.
Install the useful ones in **personal/global** Claude config instead:
- **playwright-mcp** — handy for *authoring/debugging* e2e specs interactively (not for
  running the suite, which stays terminal). Most relevant to this stack.
- chrome-devtools-mcp — overlaps playwright-mcp; pick one (prefer playwright-mcp).
- firecrawl / perplexity — general web research; no pendulum-specific value.
- graphify — code-graph; try globally, unproven, never auto-share.
rtk is already a global CLI proxy — fine as-is, also not a repo concern.

## Sequencing

(a) author `cortex-sync` skill — ✅ done (2026-06-14). (b) refresh `celer-testing` — ✅ done
(2026-06-14, §2). Both small, both propagate. Then resume Plan B.
