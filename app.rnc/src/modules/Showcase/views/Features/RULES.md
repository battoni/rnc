---
version: 1.1.2
origin: pendulum
based-on: 1.1.2
---

# RULES — FeaturesView

> For AI agents. Last updated: 2026-09-14.

## Purpose

Reports what exists in this codebase and at which version, read from the per-unit metadata
layer (`RULES.md` frontmatter + `CHANGELOG.md`). Gated on `design.read`, beside Brand and Components
under Design.

## Intentional Decisions

- **It is a FACT, never a switch.** Nothing here turns a feature on or off. In this
  architecture a feature is present because its code and its wiring are in the repo; a runtime
  flag would create a second state that can disagree with the first, and the sync knows nothing
  about it. To remove a feature, remove its wiring — and this screen will then say so.

- **The catalogue is GENERATED and committed**, by `app.rnc/scripts/build-features.mjs` into
  `features.generated.json` in this folder. `RULES.md` and `CHANGELOG.md` are repository files
  and never reach the browser, so reading them at runtime is not an option. Committing the JSON
  is the same bargain `components.d.ts` already makes: the screen cannot disagree with the repo,
  and there is no runtime cost. **Never hand-edit the JSON** — run `npm run build:features`.

- **The file lives beside its only consumer**, not under a shared `data/` directory. That
  avoids adding a path alias to `vite.config.ts`, `tsconfig.app.json` and the test configs for
  a single import, and the view that breaks when the file goes missing is the view that owns it.

- **Grouping is inferred from the path**, never maintained by hand:
  `modules/<X>` on either side becomes feature `<X>`; everything else is the shared base. A
  unit may override this with `feature: <name>` in its frontmatter, but that key should stay
  unused until a feature genuinely spans units that do not share its name — a hand-maintained
  list rots the first week somebody is in a hurry.

- **A feature has no version of its own**, only the versions of its units. The screen shows the
  newest change date across them; inventing a single feature version would be a number nobody
  could defend.

- **`origin` is the field that earns its keep downstream.** In rnc every unit reads
  `origin: rnc`, because rnc is the source. In a project it separates what was
  received from what the project wrote — which is why `specialize()` guards the exact string
  (see `cortex/sync/sync.py`, `TOKEN_GUARDS`).

- **The tooling layer is inventoried, but NOT as units.** `.claude/` and `cortex/` carry no
  `RULES.md`, no `CHANGELOG.md` and no version, and giving each skill a metadata triad would be
  bureaucracy for its own sake. The generator reads what each kind already declares — frontmatter
  where there is frontmatter, the first `#` heading where there is not, the first real comment in
  a shell script. A `cortex/` subsystem is ONE row, not the list of its files: `cortex/sync` is a
  mechanism, and enumerating its fifteen files says nothing the name did not.

- **A tooling description is cut at its first sentence.** A skill's `description` is written to
  route the model — it opens with what the thing does, then lists every trigger phrase that should
  load it. The first sentence is the catalogue row; printing the rest turned the section into a
  wall of text on the first render.

## Do Not

- Do not add write actions to this screen. If it ever needs to change something, that is a
  different screen with a different name.
- Do not read `features.generated.json` from anywhere else without moving it first and saying
  why in this file.
