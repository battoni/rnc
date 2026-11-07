# Planning documents — the shapes that travel

A product built on this bootstrap is planned in four documents before and while it is built. Their
CONTENTS belong to the project (`cortex/plans/` is LOCAL); their SHAPES are here, because each shape
was learned the expensive way in a real project and should not be re-learned in the next one.

| document | answers | when |
| --- | --- | --- |
| [`PRODUCT-MEMORY.md`](PRODUCT-MEMORY.md) | What is the product, what is decided, what is still open? | From the first ingest of the client's material, forever |
| [`ESTIMATE.md`](ESTIMATE.md) | How big is the work? | Before selling it; again per phase |
| [`ROADMAP.md`](ROADMAP.md) | In what order, and what does the bootstrap already give? | Once the product memory has its first decisions |
| [`PHASE-SPEC.md`](PHASE-SPEC.md) | What exactly does THIS phase build? | One phase at a time — only the next one |

The phase tracker itself is `cortex/plans/phases/README.md`; the unattended loop that builds a phase
is the `midnight-oil` skill, and what happens inside a run is `../OVERNIGHT-BUILD.md`.

**A convention written into a plan cites the file that already does it.** "Empty lists say what to do
next — see `OListCardGrid`'s `emptyMessage`" is a convention; "empty lists should be helpful" is a
wish. The file is what the builder copies, and a line without one is the line that gets reinvented.

## How they feed each other

```
client material ─ingest→ PRODUCT-MEMORY (⚠️ OPEN items)
                              │  /battoni-grill-me rounds close them → Decisions Log (D1, D2, …)
                              ▼
                 ESTIMATE (ranges per phase) → ROADMAP (blocks, bootstrap inventory)
                              ▼
       tracker → PHASE-SPEC for the NEXT id → build → PR with QA checklist → merge
                              │  drift check + carry-forward
                              └──────────────→ the next spec starts from what merged
```
