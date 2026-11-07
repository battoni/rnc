# RULES.md + CHANGELOG.md Schema

The per-unit metadata layer. Every **important unit** (component, view, module — frontend
and per API module — plus file-group units like build config) carries a co-located pair:

```
<unit>/
  RULES.md        ← current contract (frontmatter + body)
  CHANGELOG.md    ← versioned history (SemVer, canonical version source)
```

## Versioning

- **SemVer** `MAJOR.MINOR.PATCH`:
  - **MAJOR** — breaking change to the unit's contract (prop/emit removed or changed
    meaning, repository method renamed, response shape changed). Propagation must review
    carefully.
  - **MINOR** — additive, backwards-compatible (new optional prop, new documented rule).
  - **PATCH** — clarification, typo, non-behavioural tweak.
- **CHANGELOG.md is canonical.** You hand-write one changelog entry with its version;
  `RULES.md` frontmatter `version:` only **mirrors** the top entry. The gate enforces they
  are equal. Single edit, single source of truth.

## RULES.md frontmatter

```yaml
---
version: 1.0.0        # MUST equal the top entry in this unit's CHANGELOG.md
origin: pendulum      # where this unit originated (pendulum, or a project name if local)
based-on: 1.0.0       # the pendulum version this unit was last synced from
---
```

- **origin** — `pendulum` for anything that came from the bootstrap; a project name (e.g.
  `zion`) for units a project authored itself and that pendulum doesn't have.
- **based-on** — in *pendulum itself*, `based-on` == `version` (it is its own origin). In a
  *project*, `based-on` records the pendulum `version` last merged in; if the project then
  edits the unit and bumps `version` above `based-on`, the gap signals local divergence —
  the propagation triage flag.

After the frontmatter, the body continues exactly as today (`# RULES — X`, `## Purpose`,
`## Intentional Decisions`, etc.). Frontmatter is additive — no existing content changes.

## Accepted divergence — the number says THAT, the body says WHAT

`version > based-on` tells the triage a project changed a unit. It cannot tell the next person
syncing **what** changed or whether a sync may undo it — and a sync that "restores" a deliberate
change is the most common way a project loses one.

So a project that diverges on purpose writes it in the unit's `RULES.md` body, as a bullet that
names what the sync must not revert and why:

```markdown
- **Accepted divergence (from pendulum 1.4.0):** the demo `navigation.finances` item was removed;
  money lives in this project's own Wallet entry in `navItems.local.ts`. Do not re-add it on sync.
```

- One bullet per divergence, next to the rule it changes, with the `based-on` it left from.
- A project-authored unit (`origin: <project>`) needs none — nothing upstream competes with it.
- When the divergence is later back-ported into pendulum, the bullet goes: the unit is no longer
  divergent, and a stale "do not re-add" would block the next legitimate sync.

## CHANGELOG.md format

Keep-a-Changelog-flavoured, newest entry on top. The **top entry's version is the unit's
canonical version.**

```markdown
# Changelog — <UnitName>

All notable changes to this unit are documented here. Versioned with SemVer.
This file is the canonical source for the unit's version (RULES.md frontmatter mirrors it).

## 1.0.0 — 2026-05-30

### Added
- Initial documented baseline.
```

Entry sections use `Added` / `Changed` / `Fixed` / `Removed` as needed. Each real change
to the unit appends a new top entry and bumps the SemVer per the rules above.

## Why per-unit (not just git)

The bootstrap workflow (`clone → delete .git → git init`) destroys git history when a
project is created. A co-located CHANGELOG is the **only** history that survives the copy
into a new project — git cannot answer "what changed in this unit since the project forked"
across the repo boundary. The CHANGELOG is the portable memory; the version is the cursor
the propagation triage reads.
